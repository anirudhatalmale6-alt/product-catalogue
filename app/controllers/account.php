<?php
/**
 * The buyer side of the site: requesting access, signing in, and the small
 * account area an approved buyer sees afterwards.
 *
 * Nothing in this file can create an account that works. The public form only
 * ever writes a 'pending' row; issuing a username and password happens in the
 * admin panel, by a person, on purpose.
 */

function account_dispatch(array $segments): void
{
    // The whole feature can be switched off in Admin -> Settings, in which
    // case every one of these addresses behaves as if it was never built.
    if (!BuyerAuth::accountsEnabled()) {
        not_found();
    }

    $sub = $segments[1] ?? '';

    switch ($sub) {
        case 'login':
            account_login();
            return;

        case 'logout':
            account_logout();
            return;

        case 'password':
            account_password();
            return;

        case '':
            account_dashboard();
            return;

        default:
            not_found();
    }
}

// ---------------------------------------------------------------------------
// Requesting access
// ---------------------------------------------------------------------------

function account_request_form(): void
{
    if (!BuyerAuth::accountsEnabled()) {
        not_found();
    }
    if (BuyerAuth::check()) {
        redirect('account');
    }

    $errors = $_SESSION['access_errors'] ?? [];
    $old    = $_SESSION['access_old'] ?? [];
    unset($_SESSION['access_errors'], $_SESSION['access_old']);

    view('account/request', [
        'title'      => 'Request trade access',
        'categories' => CategoryRepository::navigation(),
        'errors'     => $errors,
        'old'        => $old,
        'noindex'    => true,
    ]);
}

function account_request_submit(): void
{
    if (!BuyerAuth::accountsEnabled()) {
        not_found();
    }
    if (!csrf_valid()) {
        $_SESSION['access_errors'] = ['email' => 'Your session expired. Please send it again.'];
        redirect('request-access');
    }

    // Same honeypot as the enquiry form: a field no human sees, so a bot that
    // fills every input gets a confirmation page and writes nothing.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        redirect('access-requested');
    }

    $data = [
        'contact_name' => trim((string) ($_POST['contact_name'] ?? '')),
        'email'        => trim((string) ($_POST['email'] ?? '')),
        'company'      => trim((string) ($_POST['company'] ?? '')),
        'phone'        => trim((string) ($_POST['phone'] ?? '')),
        'country'      => trim((string) ($_POST['country'] ?? '')),
        'interest'     => trim((string) ($_POST['interest'] ?? '')),
    ];

    $errors = [];
    if ($data['contact_name'] === '') {
        $errors['contact_name'] = 'Please tell us your name.';
    }
    if ($data['email'] === '') {
        $errors['email'] = 'Please give us an email address.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That does not look like an email address.';
    }
    if ($data['company'] === '') {
        $errors['company'] = 'Please tell us which company you are buying for.';
    }

    if ($errors) {
        $_SESSION['access_errors'] = $errors;
        $_SESSION['access_old']    = $data;
        redirect('request-access');
    }

    // An address that has already applied is NOT told so. Otherwise this form
    // becomes a way of asking "does this company already buy from you?", which
    // is a question a competitor would enjoy being able to ask. They get the
    // same confirmation page either way and the existing row is left exactly
    // as it is - in particular a second application cannot reset a rejected
    // one back to pending.
    $existing = BuyerRepository::findByEmail($data['email']);
    if ($existing) {
        redirect('access-requested');
    }

    try {
        $id = BuyerRepository::createApplication($data);
    } catch (Throwable $e) {
        error_log('Access request failed: ' . $e->getMessage());
        $_SESSION['access_errors'] = ['email' =>
            'Something went wrong saving your request. Please try again.'];
        $_SESSION['access_old'] = $data;
        redirect('request-access');
    }

    account_notify_new_request($id, $data);
    redirect('access-requested');
}

function account_request_sent(): void
{
    view('account/request_sent', [
        'title'      => 'Request received',
        'categories' => CategoryRepository::navigation(),
        'noindex'    => true,
    ]);
}

/**
 * Tells the site owner a request is waiting, reusing the same address the
 * enquiry alerts go to. As with enquiries this is a convenience: the request
 * is already in the admin panel before this runs, so a mail failure is logged
 * and never shown to the applicant.
 */
function account_notify_new_request(int $id, array $data): void
{
    $to = trim((string) setting('enquiry_notify_email', ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $body = "A new trade access request is waiting for review.\n\n"
          . "Name:    {$data['contact_name']}\n"
          . "Company: {$data['company']}\n"
          . "Email:   {$data['email']}\n"
          . 'Phone:   ' . ($data['phone'] ?: '-') . "\n"
          . 'Country: ' . ($data['country'] ?: '-') . "\n\n"
          . ($data['interest'] ? "What they are looking for:\n{$data['interest']}\n\n" : '')
          . "Nobody can sign in until you approve it. Review it here:\n"
          . absolute_url('admin/buyers/' . $id) . "\n";

    $host = preg_replace('/[^A-Za-z0-9.\-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $headers = 'From: no-reply@' . ($host ?: 'localhost') . "\r\n"
             . 'Reply-To: ' . str_replace(["\r", "\n"], '', $data['email']) . "\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    if (!@mail($to, 'Trade access request - ' . $data['company'], $body, $headers)) {
        error_log('Access request ' . $id . ' saved but the notification email failed.');
    }
}

// ---------------------------------------------------------------------------
// Signing in
// ---------------------------------------------------------------------------

function account_login(): void
{
    if (BuyerAuth::check()) {
        redirect('account');
    }
    $error = null;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
        $error = BuyerAuth::attempt(
            trim((string) ($_POST['login'] ?? '')),
            (string) ($_POST['password'] ?? '')
        );
        if ($error === null) {
            $intended = $_SESSION['buyer_intended'] ?? 'account';
            unset($_SESSION['buyer_intended']);
            // Straight to the change-password page while the emailed password
            // is still the only one that works.
            $user = BuyerAuth::user();
            redirect(!empty($user['must_change_password']) ? 'account/password' : $intended);
        }
    }

    view('account/login', [
        'title'      => 'Buyer sign in',
        'categories' => CategoryRepository::navigation(),
        'error'      => $error,
        'noindex'    => true,
    ]);
}

function account_logout(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
    }
    BuyerAuth::logout();
    flash('success', 'You have been signed out.');
    redirect('');
}

// ---------------------------------------------------------------------------
// The account area
// ---------------------------------------------------------------------------

function account_dashboard(): void
{
    BuyerAuth::requireLogin();
    $user = BuyerAuth::user();

    // Their own enquiry history, matched on the address they signed in with.
    $enquiries = Database::all(
        'SELECT e.id, e.reference, e.status, e.created_at,
                (SELECT COUNT(*) FROM enquiry_items i WHERE i.enquiry_id = e.id) AS item_count
           FROM enquiries e
          WHERE e.email = ?
          ORDER BY e.created_at DESC
          LIMIT 50',
        [$user['email']]);

    view('account/dashboard', [
        'title'      => 'Your account',
        'categories' => CategoryRepository::navigation(),
        'user'       => $user,
        'enquiries'  => $enquiries,
        'noindex'    => true,
    ]);
}

function account_password(): void
{
    BuyerAuth::requireLogin();
    $user   = BuyerAuth::user();
    $errors = [];

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        // Only ask for the current password once they have chosen one of their
        // own. Demanding it on the very first visit would mean retyping the
        // generated password they just used to get here.
        if (empty($user['must_change_password'])) {
            $row = Database::one(
                'SELECT password_hash FROM buyer_accounts WHERE id = ?', [$user['id']]);
            if (!password_verify((string) ($_POST['current_password'] ?? ''),
                                 (string) ($row['password_hash'] ?? ''))) {
                $errors['current_password'] = 'That is not your current password.';
            }
        }

        if (strlen($new) < 10) {
            $errors['new_password'] = 'Please choose at least 10 characters.';
        }
        if ($new !== $confirm) {
            $errors['confirm_password'] = 'The two passwords do not match.';
        }

        if (!$errors) {
            BuyerRepository::setPassword((int) $user['id'], $new);
            flash('success', 'Your password has been changed.');
            redirect('account');
        }
    }

    view('account/password', [
        'title'      => 'Change your password',
        'categories' => CategoryRepository::navigation(),
        'user'       => $user,
        'errors'     => $errors,
        'noindex'    => true,
    ]);
}

/**
 * The page a visitor sees when the whole catalogue is behind the login.
 * Deliberately a real page with a 200 rather than a redirect loop, so a link
 * shared by a buyer still lands somewhere that explains itself.
 */
function account_gate_page(): void
{
    view('account/gate', [
        'title'      => 'Trade access required',
        'categories' => [],
        'noindex'    => true,
    ]);
}
