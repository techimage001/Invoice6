<?php
require_once __DIR__ . '/config.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name(SESSION_NAME);
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
    'path' => '/',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* Search-engine verification. Paste a value in when you verify with each service.
   Empty entries emit nothing, so there are never dangling meta tags. */
define('VERIFY', [
    'google-site-verification' => '',
    'msvalidate.01'           => '',
    'yandex-verification'     => '',
    'naver-site-verification' => '',
    'baidu-site-verification' => '',
    'seznam-wmt'              => '',
    'p:domain_verify'         => '',
]);

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $db): void {
    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 email TEXT NOT NULL UNIQUE,
 password_hash TEXT NOT NULL,
 business_name TEXT NOT NULL,
 plan TEXT NOT NULL DEFAULT 'starter',
 account_status TEXT NOT NULL DEFAULT 'trial',
 trial_ends_at TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS settings (
 user_id INTEGER PRIMARY KEY,
 country_code TEXT NOT NULL DEFAULT 'GB',
 business_name TEXT,
 business_email TEXT,
 business_phone TEXT,
 business_address TEXT,
 bank_details TEXT,
 currency TEXT NOT NULL DEFAULT 'GBP',
 currency_name TEXT NOT NULL DEFAULT 'Pound sterling',
 currency_symbol TEXT NOT NULL DEFAULT '£',
 currency_symbol_position TEXT NOT NULL DEFAULT 'before',
 currency_decimal_separator TEXT NOT NULL DEFAULT '.',
 currency_thousands_separator TEXT NOT NULL DEFAULT ',',
 currency_decimals INTEGER NOT NULL DEFAULT 2,
 quote_prefix TEXT NOT NULL DEFAULT 'Q-',
 invoice_prefix TEXT NOT NULL DEFAULT 'INV-',
 default_tax_rate REAL NOT NULL DEFAULT 0,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS enquiries (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL,
 name TEXT NOT NULL,
 email TEXT,
 phone TEXT,
 source TEXT,
 details TEXT,
 estimated_value REAL NOT NULL DEFAULT 0,
 status TEXT NOT NULL DEFAULT 'new',
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS customers (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL,
 name TEXT NOT NULL,
 email TEXT,
 phone TEXT,
 address TEXT,
 notes TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS quotes (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL,
 customer_id INTEGER NOT NULL,
 quote_number TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'draft',
 issue_date TEXT NOT NULL,
 expiry_date TEXT,
 subtotal REAL NOT NULL DEFAULT 0,
 tax_rate REAL NOT NULL DEFAULT 0,
 tax REAL NOT NULL DEFAULT 0,
 total REAL NOT NULL DEFAULT 0,
 notes TEXT,
 public_token TEXT NOT NULL UNIQUE,
 next_followup_at TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS quote_items (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 quote_id INTEGER NOT NULL,
 description TEXT NOT NULL,
 qty REAL NOT NULL DEFAULT 1,
 unit_price REAL NOT NULL DEFAULT 0,
 line_total REAL NOT NULL DEFAULT 0,
 FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS jobs (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL,
 customer_id INTEGER NOT NULL,
 quote_id INTEGER,
 title TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'scheduled',
 start_date TEXT,
 end_date TEXT,
 notes TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS invoices (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL,
 customer_id INTEGER NOT NULL,
 quote_id INTEGER,
 job_id INTEGER,
 invoice_number TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'unpaid',
 issue_date TEXT NOT NULL,
 due_date TEXT,
 subtotal REAL NOT NULL DEFAULT 0,
 tax_rate REAL NOT NULL DEFAULT 0,
 tax REAL NOT NULL DEFAULT 0,
 total REAL NOT NULL DEFAULT 0,
 paid_at TEXT,
 notes TEXT,
 public_token TEXT NOT NULL UNIQUE,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE SET NULL,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS invoice_items (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 invoice_id INTEGER NOT NULL,
 description TEXT NOT NULL,
 qty REAL NOT NULL DEFAULT 1,
 unit_price REAL NOT NULL DEFAULT 0,
 line_total REAL NOT NULL DEFAULT 0,
 FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS followups (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL,
 entity_type TEXT NOT NULL,
 entity_id INTEGER NOT NULL,
 due_at TEXT NOT NULL,
 kind TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'open',
 note TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS expenses (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL,
 job_id INTEGER,
 description TEXT NOT NULL,
 amount REAL NOT NULL DEFAULT 0,
 expense_date TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS contact_messages (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 name TEXT NOT NULL,
 email TEXT NOT NULL,
 message TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS notifications (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL,
 kind TEXT NOT NULL,
 message TEXT NOT NULL,
 entity_type TEXT,
 entity_id INTEGER,
 read_at TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS login_attempts (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 email TEXT NOT NULL,
 ip_hash TEXT NOT NULL,
 attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS password_resets (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL,
 token_hash TEXT NOT NULL UNIQUE,
 expires_at TEXT NOT NULL,
 used_at TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS email_verifications (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL,
 token_hash TEXT NOT NULL UNIQUE,
 expires_at TEXT NOT NULL,
 used_at TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS recurring_jobs (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL,
 customer_id INTEGER NOT NULL,
 title TEXT NOT NULL,
 frequency TEXT NOT NULL DEFAULT 'monthly',
 next_run_date TEXT NOT NULL,
 default_amount REAL NOT NULL DEFAULT 0,
 active INTEGER NOT NULL DEFAULT 1,
 notes TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS rate_limits (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 action TEXT NOT NULL,
 ip_hash TEXT NOT NULL,
 attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS audit_log (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER,
 action TEXT NOT NULL,
 entity_type TEXT,
 entity_id INTEGER,
 detail TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_enquiries_user_status ON enquiries(user_id,status);
CREATE INDEX IF NOT EXISTS idx_customers_user ON customers(user_id);
CREATE INDEX IF NOT EXISTS idx_quotes_user_status ON quotes(user_id,status);
CREATE INDEX IF NOT EXISTS idx_invoices_user_status ON invoices(user_id,status);
CREATE INDEX IF NOT EXISTS idx_jobs_user_status ON jobs(user_id,status);
CREATE INDEX IF NOT EXISTS idx_followups_user_due ON followups(user_id,status,due_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_lookup ON login_attempts(email,ip_hash,attempted_at);
CREATE INDEX IF NOT EXISTS idx_password_resets_user ON password_resets(user_id,expires_at);
CREATE TABLE IF NOT EXISTS mail_log (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 to_addr TEXT NOT NULL,
 subject TEXT NOT NULL,
 transport TEXT NOT NULL,
 ok INTEGER NOT NULL DEFAULT 0,
 error TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_mail_log_created ON mail_log(created_at);
CREATE TABLE IF NOT EXISTS reminder_log (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL,
 invoice_id INTEGER NOT NULL,
 stage TEXT NOT NULL,
 sent_to TEXT NOT NULL,
 sent_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_reminder_once ON reminder_log(invoice_id,stage);
CREATE TABLE IF NOT EXISTS login_links (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL,
 token_hash TEXT NOT NULL,
 expires_at TEXT NOT NULL,
 used_at TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_login_links_user ON login_links(user_id,expires_at);
CREATE INDEX IF NOT EXISTS idx_email_verifications_user ON email_verifications(user_id,expires_at);
CREATE INDEX IF NOT EXISTS idx_recurring_jobs_due ON recurring_jobs(user_id,active,next_run_date);
CREATE INDEX IF NOT EXISTS idx_rate_limits_lookup ON rate_limits(action,ip_hash,attempted_at);
SQL;
    $db->exec($sql);
    $columns=$db->query('PRAGMA table_info(users)')->fetchAll();$names=array_column($columns,'name');
    if(!in_array('email_verified_at',$names,true))$db->exec('ALTER TABLE users ADD COLUMN email_verified_at TEXT');
    $columns=$db->query('PRAGMA table_info(settings)')->fetchAll();$names=array_column($columns,'name');
    $currencyColumns=[
        'country_code'=>"TEXT NOT NULL DEFAULT 'GB'",
        'currency_name'=>"TEXT NOT NULL DEFAULT 'Pound sterling'",
        'currency_symbol'=>"TEXT NOT NULL DEFAULT '£'",
        'currency_symbol_position'=>"TEXT NOT NULL DEFAULT 'before'",
        'currency_decimal_separator'=>"TEXT NOT NULL DEFAULT '.'",
        'currency_thousands_separator'=>"TEXT NOT NULL DEFAULT ','",
        'currency_decimals'=>"INTEGER NOT NULL DEFAULT 2",
    ];
    foreach($currencyColumns as $name=>$definition)if(!in_array($name,$names,true))$db->exec("ALTER TABLE settings ADD COLUMN $name $definition");
    if(!in_array('business_logo',$names,true))$db->exec("ALTER TABLE settings ADD COLUMN business_logo TEXT");
    // Free-while-growing: no account is gated on a trial. Existing trial accounts become active.
    $db->exec("UPDATE users SET account_status='active', trial_ends_at=NULL WHERE account_status='trial'");
}

function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . $path); exit; }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function require_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Session expired. Please go back and try again.'); } }
function flash(string $type, string $msg): void { $_SESSION['flash'][]=['type'=>$type,'msg'=>$msg]; }
function flashes(): array { $x=$_SESSION['flash']??[]; unset($_SESSION['flash']); return $x; }
/* require_login(), user() and uid() were removed: no code path creates a users
   session any more. Access is verified email only (see api/). */
/* V58: safe no-session stubs. The users session was removed (access is verified
   email only), but is_admin(), get_settings(), next_number(), audit() and owned()
   still reference these helpers. Returning empty keeps every caller on its
   existing no-user branch instead of throwing a fatal 500. */
function user(): ?array { return null; }
function uid(): int { return 0; }
function reviews(): array {
    $f=__DIR__.'/reviews.php';
    if(!is_file($f)) return [];
    $data=include $f; return is_array($data)?$data:[];
}
function logo_src(?string $stored): string {
    $stored=ltrim(trim((string)$stored),'/');
    if($stored===''||!preg_match('#^uploads/logos/[A-Za-z0-9._-]+$#',$stored)) return '';
    return '/'.$stored;
}
function is_admin(): bool { return user() && ADMIN_EMAIL && strcasecmp(user()['email'], ADMIN_EMAIL)===0; }
function currency_catalog(): array {
    return [
        'AED'=>['UAE dirham','AED','before',2],
        'AFN'=>['Afghan afghani','؋','before',2],
        'ALL'=>['Albanian lek','L','after',2],
        'AMD'=>['Armenian dram','֏','after',2],
        'AOA'=>['Angolan kwanza','Kz','before',2],
        'ARS'=>['Argentine peso','AR$','before',2],
        'AUD'=>['Australian dollar','A$','before',2],
        'AWG'=>['Aruban florin','ƒ','before',2],
        'AZN'=>['Azerbaijani manat','₼','after',2],
        'BAM'=>['Bosnia-Herzegovina mark','KM','before',2],
        'BBD'=>['Barbadian dollar','Bds$','before',2],
        'BDT'=>['Bangladeshi taka','৳','before',2],
        'BHD'=>['Bahraini dinar','BD','before',3],
        'BIF'=>['Burundian franc','FBu','before',0],
        'BMD'=>['Bermudian dollar','BD$','before',2],
        'BND'=>['Brunei dollar','B$','before',2],
        'BOB'=>['Bolivian boliviano','Bs','before',2],
        'BRL'=>['Brazilian real','R$','before',2],
        'BSD'=>['Bahamian dollar','B$','before',2],
        'BTN'=>['Bhutanese ngultrum','BTN','before',2],
        'BWP'=>['Botswana pula','P','before',2],
        'BYN'=>['Belarusian ruble','Br','before',2],
        'BZD'=>['Belize dollar','BZ$','before',2],
        'CAD'=>['Canadian dollar','C$','before',2],
        'CDF'=>['Congolese franc','FC','before',2],
        'CHF'=>['Swiss franc','CHF','before',2],
        'CLP'=>['Chilean peso','CLP$','before',0],
        'CNY'=>['Chinese yuan','CN¥','before',2],
        'COP'=>['Colombian peso','COL$','before',2],
        'CRC'=>['Costa Rican colon','₡','before',2],
        'CUP'=>['Cuban peso','$MN','before',2],
        'CVE'=>['Cape Verdean escudo','$','before',2],
        'CZK'=>['Czech koruna','Kč','after',2],
        'DJF'=>['Djiboutian franc','Fdj','before',0],
        'DKK'=>['Danish krone','kr','after',2],
        'DOP'=>['Dominican peso','RD$','before',2],
        'DZD'=>['Algerian dinar','DA','before',2],
        'EGP'=>['Egyptian pound','E£','before',2],
        'ERN'=>['Eritrean nakfa','Nfk','before',2],
        'ETB'=>['Ethiopian birr','Br','before',2],
        'EUR'=>['Euro','€','before',2],
        'FJD'=>['Fijian dollar','FJ$','before',2],
        'FKP'=>['Falkland Islands pound','£','before',2],
        'GBP'=>['Pound sterling','£','before',2],
        'GEL'=>['Georgian lari','₾','after',2],
        'GHS'=>['Ghanaian cedi','GH₵','before',2],
        'GIP'=>['Gibraltar pound','£','before',2],
        'GMD'=>['Gambian dalasi','D','before',2],
        'GNF'=>['Guinean franc','FG','before',0],
        'GTQ'=>['Guatemalan quetzal','Q','before',2],
        'GYD'=>['Guyanese dollar','G$','before',2],
        'HKD'=>['Hong Kong dollar','HK$','before',2],
        'HNL'=>['Honduran lempira','L','before',2],
        'HTG'=>['Haitian gourde','G','before',2],
        'HUF'=>['Hungarian forint','Ft','after',2],
        'IDR'=>['Indonesian rupiah','Rp','before',2],
        'ILS'=>['Israeli shekel','₪','before',2],
        'INR'=>['Indian rupee','₹','before',2],
        'IQD'=>['Iraqi dinar','ID','before',3],
        'IRR'=>['Iranian rial','﷼','before',2],
        'ISK'=>['Icelandic krona','kr','after',0],
        'JMD'=>['Jamaican dollar','J$','before',2],
        'JOD'=>['Jordanian dinar','JD','before',3],
        'JPY'=>['Japanese yen','¥','before',0],
        'KES'=>['Kenyan shilling','KSh','before',2],
        'KGS'=>['Kyrgyzstani som','с','after',2],
        'KHR'=>['Cambodian riel','៛','before',2],
        'KMF'=>['Comorian franc','CF','before',0],
        'KPW'=>['North Korean won','₩','before',2],
        'KRW'=>['South Korean won','₩','before',0],
        'KWD'=>['Kuwaiti dinar','KD','before',3],
        'KYD'=>['Cayman Islands dollar','CI$','before',2],
        'KZT'=>['Kazakhstani tenge','₸','after',2],
        'LAK'=>['Lao kip','₭','before',2],
        'LBP'=>['Lebanese pound','L£','before',2],
        'LKR'=>['Sri Lankan rupee','₨','before',2],
        'LRD'=>['Liberian dollar','L$','before',2],
        'LSL'=>['Lesotho loti','L','before',2],
        'LYD'=>['Libyan dinar','LD','before',3],
        'MAD'=>['Moroccan dirham','MAD','before',2],
        'MDL'=>['Moldovan leu','L','before',2],
        'MGA'=>['Malagasy ariary','Ar','before',2],
        'MKD'=>['Macedonian denar','ден','after',2],
        'MMK'=>['Myanmar kyat','K','before',2],
        'MNT'=>['Mongolian tugrik','₮','after',2],
        'MOP'=>['Macanese pataca','MOP$','before',2],
        'MRU'=>['Mauritanian ouguiya','UM','before',2],
        'MUR'=>['Mauritian rupee','₨','before',2],
        'MVR'=>['Maldivian rufiyaa','Rf','before',2],
        'MWK'=>['Malawian kwacha','MK','before',2],
        'MXN'=>['Mexican peso','Mex$','before',2],
        'MYR'=>['Malaysian ringgit','RM','before',2],
        'MZN'=>['Mozambican metical','MT','before',2],
        'NAD'=>['Namibian dollar','N$','before',2],
        'NGN'=>['Nigerian naira','₦','before',2],
        'NIO'=>['Nicaraguan cordoba','C$','before',2],
        'NOK'=>['Norwegian krone','kr','after',2],
        'NPR'=>['Nepalese rupee','रू','before',2],
        'NZD'=>['New Zealand dollar','NZ$','before',2],
        'OMR'=>['Omani rial','OMR','before',3],
        'PAB'=>['Panamanian balboa','B/.','before',2],
        'PEN'=>['Peruvian sol','S/','before',2],
        'PGK'=>['Papua New Guinean kina','K','before',2],
        'PHP'=>['Philippine peso','₱','before',2],
        'PKR'=>['Pakistani rupee','₨','before',2],
        'PLN'=>['Polish zloty','zł','after',2],
        'PYG'=>['Paraguayan guarani','₲','before',0],
        'QAR'=>['Qatari riyal','QAR','before',2],
        'RON'=>['Romanian leu','lei','after',2],
        'RSD'=>['Serbian dinar','дин','after',2],
        'RUB'=>['Russian ruble','₽','after',2],
        'RWF'=>['Rwandan franc','FRw','before',0],
        'SAR'=>['Saudi riyal','SAR','before',2],
        'SBD'=>['Solomon Islands dollar','SI$','before',2],
        'SCR'=>['Seychellois rupee','₨','before',2],
        'SDG'=>['Sudanese pound','SDG','before',2],
        'SEK'=>['Swedish krona','kr','after',2],
        'SGD'=>['Singapore dollar','S$','before',2],
        'SHP'=>['Saint Helena pound','£','before',2],
        'SLE'=>['Sierra Leonean leone','Le','before',2],
        'SOS'=>['Somali shilling','Sh','before',2],
        'SRD'=>['Surinamese dollar','Sr$','before',2],
        'SSP'=>['South Sudanese pound','SS£','before',2],
        'STN'=>['Sao Tome dobra','Db','before',2],
        'SYP'=>['Syrian pound','S£','before',2],
        'SZL'=>['Swazi lilangeni','L','before',2],
        'THB'=>['Thai baht','฿','before',2],
        'TJS'=>['Tajikistani somoni','SM','after',2],
        'TMT'=>['Turkmenistani manat','m','before',2],
        'TND'=>['Tunisian dinar','DT','before',3],
        'TOP'=>['Tongan paanga','T$','before',2],
        'TRY'=>['Turkish lira','₺','before',2],
        'TTD'=>['Trinidad and Tobago dollar','TT$','before',2],
        'TWD'=>['New Taiwan dollar','NT$','before',2],
        'TZS'=>['Tanzanian shilling','TSh','before',2],
        'UAH'=>['Ukrainian hryvnia','₴','after',2],
        'UGX'=>['Ugandan shilling','USh','before',0],
        'USD'=>['US dollar','$','before',2],
        'UYU'=>['Uruguayan peso','$U','before',2],
        'UZS'=>['Uzbekistani som','soʻm','after',2],
        'VES'=>['Venezuelan bolivar','Bs.','before',2],
        'VND'=>['Vietnamese dong','₫','after',0],
        'VUV'=>['Vanuatu vatu','VT','after',0],
        'WST'=>['Samoan tala','WS$','before',2],
        'XAF'=>['Central African CFA franc','FCFA','after',0],
        'XCD'=>['East Caribbean dollar','EC$','before',2],
        'XCG'=>['Caribbean guilder','ƒ','before',2],
        'XOF'=>['West African CFA franc','CFA','after',0],
        'XPF'=>['CFP franc','₣','after',0],
        'YER'=>['Yemeni rial','﷼','before',2],
        'ZAR'=>['South African rand','R','before',2],
        'ZMW'=>['Zambian kwacha','ZK','before',2],
        'ZWG'=>['Zimbabwe gold','Z$','before',2],
    ];
}
function country_currency_map(): array {
    return [
        'AD'=>'EUR', 'AE'=>'AED', 'AF'=>'AFN', 'AG'=>'XCD', 'AI'=>'XCD', 'AL'=>'ALL', 'AM'=>'AMD', 'AO'=>'AOA',
        'AQ'=>'USD', 'AR'=>'ARS', 'AS'=>'USD', 'AT'=>'EUR', 'AU'=>'AUD', 'AW'=>'AWG', 'AX'=>'EUR', 'AZ'=>'AZN',
        'BA'=>'BAM', 'BB'=>'BBD', 'BD'=>'BDT', 'BE'=>'EUR', 'BF'=>'XOF', 'BG'=>'EUR', 'BH'=>'BHD', 'BI'=>'BIF',
        'BJ'=>'XOF', 'BL'=>'EUR', 'BM'=>'BMD', 'BN'=>'BND', 'BO'=>'BOB', 'BQ'=>'USD', 'BR'=>'BRL', 'BS'=>'BSD',
        'BT'=>'BTN', 'BV'=>'NOK', 'BW'=>'BWP', 'BY'=>'BYN', 'BZ'=>'BZD', 'CA'=>'CAD', 'CC'=>'AUD', 'CD'=>'CDF',
        'CF'=>'XAF', 'CG'=>'XAF', 'CH'=>'CHF', 'CI'=>'XOF', 'CK'=>'NZD', 'CL'=>'CLP', 'CM'=>'XAF', 'CN'=>'CNY',
        'CO'=>'COP', 'CR'=>'CRC', 'CU'=>'CUP', 'CV'=>'CVE', 'CW'=>'XCG', 'CX'=>'AUD', 'CY'=>'EUR', 'CZ'=>'CZK',
        'DE'=>'EUR', 'DJ'=>'DJF', 'DK'=>'DKK', 'DM'=>'XCD', 'DO'=>'DOP', 'DZ'=>'DZD', 'EC'=>'USD', 'EE'=>'EUR',
        'EG'=>'EGP', 'EH'=>'MAD', 'ER'=>'ERN', 'ES'=>'EUR', 'ET'=>'ETB', 'FI'=>'EUR', 'FJ'=>'FJD', 'FK'=>'FKP',
        'FM'=>'USD', 'FO'=>'DKK', 'FR'=>'EUR', 'GA'=>'XAF', 'GB'=>'GBP', 'GD'=>'XCD', 'GE'=>'GEL', 'GF'=>'EUR',
        'GG'=>'GBP', 'GH'=>'GHS', 'GI'=>'GIP', 'GL'=>'DKK', 'GM'=>'GMD', 'GN'=>'GNF', 'GP'=>'EUR', 'GQ'=>'XAF',
        'GR'=>'EUR', 'GS'=>'FKP', 'GT'=>'GTQ', 'GU'=>'USD', 'GW'=>'XOF', 'GY'=>'GYD', 'HK'=>'HKD', 'HM'=>'AUD',
        'HN'=>'HNL', 'HR'=>'EUR', 'HT'=>'HTG', 'HU'=>'HUF', 'ID'=>'IDR', 'IE'=>'EUR', 'IL'=>'ILS', 'IM'=>'GBP',
        'IN'=>'INR', 'IO'=>'USD', 'IQ'=>'IQD', 'IR'=>'IRR', 'IS'=>'ISK', 'IT'=>'EUR', 'JE'=>'GBP', 'JM'=>'JMD',
        'JO'=>'JOD', 'JP'=>'JPY', 'KE'=>'KES', 'KG'=>'KGS', 'KH'=>'KHR', 'KI'=>'AUD', 'KM'=>'KMF', 'KN'=>'XCD',
        'KP'=>'KPW', 'KR'=>'KRW', 'KW'=>'KWD', 'KY'=>'KYD', 'KZ'=>'KZT', 'LA'=>'LAK', 'LB'=>'LBP', 'LC'=>'XCD',
        'LI'=>'CHF', 'LK'=>'LKR', 'LR'=>'LRD', 'LS'=>'LSL', 'LT'=>'EUR', 'LU'=>'EUR', 'LV'=>'EUR', 'LY'=>'LYD',
        'MA'=>'MAD', 'MC'=>'EUR', 'MD'=>'MDL', 'ME'=>'EUR', 'MF'=>'EUR', 'MG'=>'MGA', 'MH'=>'USD', 'MK'=>'MKD',
        'ML'=>'XOF', 'MM'=>'MMK', 'MN'=>'MNT', 'MO'=>'MOP', 'MP'=>'USD', 'MQ'=>'EUR', 'MR'=>'MRU', 'MS'=>'XCD',
        'MT'=>'EUR', 'MU'=>'MUR', 'MV'=>'MVR', 'MW'=>'MWK', 'MX'=>'MXN', 'MY'=>'MYR', 'MZ'=>'MZN', 'NA'=>'NAD',
        'NC'=>'XPF', 'NE'=>'XOF', 'NF'=>'AUD', 'NG'=>'NGN', 'NI'=>'NIO', 'NL'=>'EUR', 'NO'=>'NOK', 'NP'=>'NPR',
        'NR'=>'AUD', 'NU'=>'NZD', 'NZ'=>'NZD', 'OM'=>'OMR', 'PA'=>'PAB', 'PE'=>'PEN', 'PF'=>'XPF', 'PG'=>'PGK',
        'PH'=>'PHP', 'PK'=>'PKR', 'PL'=>'PLN', 'PM'=>'EUR', 'PN'=>'NZD', 'PR'=>'USD', 'PS'=>'ILS', 'PT'=>'EUR',
        'PW'=>'USD', 'PY'=>'PYG', 'QA'=>'QAR', 'RE'=>'EUR', 'RO'=>'RON', 'RS'=>'RSD', 'RU'=>'RUB', 'RW'=>'RWF',
        'SA'=>'SAR', 'SB'=>'SBD', 'SC'=>'SCR', 'SD'=>'SDG', 'SE'=>'SEK', 'SG'=>'SGD', 'SH'=>'SHP', 'SI'=>'EUR',
        'SJ'=>'NOK', 'SK'=>'EUR', 'SL'=>'SLE', 'SM'=>'EUR', 'SN'=>'XOF', 'SO'=>'SOS', 'SR'=>'SRD', 'SS'=>'SSP',
        'ST'=>'STN', 'SV'=>'USD', 'SX'=>'XCG', 'SY'=>'SYP', 'SZ'=>'SZL', 'TC'=>'USD', 'TD'=>'XAF', 'TF'=>'EUR',
        'TG'=>'XOF', 'TH'=>'THB', 'TJ'=>'TJS', 'TK'=>'NZD', 'TL'=>'USD', 'TM'=>'TMT', 'TN'=>'TND', 'TO'=>'TOP',
        'TR'=>'TRY', 'TT'=>'TTD', 'TV'=>'AUD', 'TW'=>'TWD', 'TZ'=>'TZS', 'UA'=>'UAH', 'UG'=>'UGX', 'UM'=>'USD',
        'US'=>'USD', 'UY'=>'UYU', 'UZ'=>'UZS', 'VA'=>'EUR', 'VC'=>'XCD', 'VE'=>'VES', 'VG'=>'USD', 'VI'=>'USD',
        'VN'=>'VND', 'VU'=>'VUV', 'WF'=>'XPF', 'WS'=>'WST', 'XK'=>'EUR', 'YE'=>'YER', 'YT'=>'EUR', 'ZA'=>'ZAR',
        'ZM'=>'ZMW', 'ZW'=>'ZWG',
    ];
}
function clean_currency_symbol(string $value): string {
    $value=preg_replace('/[<>&"\'\x00-\x1F\x7F]/u','',trim($value))??'';
    return function_exists('mb_substr')?mb_substr($value,0,12):substr($value,0,24);
}
function format_money(float $n,array $settings): string {
    $code=strtoupper(preg_replace('/[^A-Z0-9]/','',(string)($settings['currency']??'GBP'))?:'GBP');
    $catalog=currency_catalog();$preset=$catalog[$code]??[$code,$code,'before',2];
    $symbol=clean_currency_symbol((string)($settings['currency_symbol']??$preset[1]))?:$code;
    $position=in_array(($settings['currency_symbol_position']??$preset[2]),['before','after'],true)?($settings['currency_symbol_position']??$preset[2]):'before';
    $decimals=max(0,min(4,(int)($settings['currency_decimals']??$preset[3])));
    $decimal=($settings['currency_decimal_separator']??'.')===','?',':'.';
    $thousands=($settings['currency_thousands_separator']??',');
    if(!in_array($thousands,[',','.',' ',''],true)||$thousands===$decimal)$thousands='';
    $amount=number_format($n,$decimals,$decimal,$thousands);
    return $position==='after'?$amount.' '.$symbol:$symbol.$amount;
}
function money(float $n, ?string $currency=null): string {
    if($currency!==null){$catalog=currency_catalog();$preset=$catalog[$currency]??[$currency,$currency,'before',2];return format_money($n,['currency'=>$currency,'currency_symbol'=>$preset[1],'currency_symbol_position'=>$preset[2],'currency_decimals'=>$preset[3]]);}
    return format_money($n,get_settings());
}
function get_settings(): array {
    static $cache=[]; $id=uid(); if(!$id) return ['currency'=>'GBP'];
    if(isset($cache[$id])) return $cache[$id];
    $st=db()->prepare('SELECT * FROM settings WHERE user_id=?'); $st->execute([$id]);
    return $cache[$id]=$st->fetch() ?: ['currency'=>'GBP'];
}
function next_number(string $type): string {
    $s=get_settings(); $table=$type==='quote'?'quotes':'invoices'; $prefix=$type==='quote'?($s['quote_prefix']??'Q-'):($s['invoice_prefix']??'INV-');
    $st=db()->prepare("SELECT COUNT(*) FROM $table WHERE user_id=?"); $st->execute([uid()]);
    return $prefix . str_pad((string)(((int)$st->fetchColumn())+1), 5, '0', STR_PAD_LEFT);
}
function rand_token(): string { return bin2hex(random_bytes(20)); }
function login_ip_hash(): string { return hash('sha256',($_SERVER['REMOTE_ADDR']??'unknown').'|'.SESSION_NAME); }
function login_is_limited(string $email): bool {
    $st=db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE email=? AND ip_hash=? AND attempted_at>=datetime('now','-15 minutes')");
    $st->execute([$email,login_ip_hash()]); return (int)$st->fetchColumn()>=5;
}
function record_login_failure(string $email): void {
    db()->prepare('INSERT INTO login_attempts(email,ip_hash) VALUES(?,?)')->execute([$email,login_ip_hash()]);
    if(random_int(1,20)===1) db()->exec("DELETE FROM login_attempts WHERE attempted_at<datetime('now','-1 day')");
}
function clear_login_failures(string $email): void { db()->prepare('DELETE FROM login_attempts WHERE email=? AND ip_hash=?')->execute([$email,login_ip_hash()]); }
function rate_limited(string $action,int $max=5,int $minutes=15): bool {
    $st=db()->prepare("SELECT COUNT(*) FROM rate_limits WHERE action=? AND ip_hash=? AND attempted_at>=datetime('now',?)");
    $st->execute([$action,login_ip_hash(),'-'.$minutes.' minutes']); return (int)$st->fetchColumn()>=$max;
}
function record_rate(string $action): void { db()->prepare('INSERT INTO rate_limits(action,ip_hash) VALUES(?,?)')->execute([$action,login_ip_hash()]); }
function audit(string $action,?string $type=null,?int $id=null,string $detail=''): void { db()->prepare('INSERT INTO audit_log(user_id,action,entity_type,entity_id,detail) VALUES(?,?,?,?,?)')->execute([uid()?:null,$action,$type,$id,$detail]); }
function create_verification(int $userId,string $email): void {
    $raw=rand_token();$hash=hash('sha256',$raw);db()->prepare("UPDATE email_verifications SET used_at=datetime('now') WHERE user_id=? AND used_at IS NULL")->execute([$userId]);
    db()->prepare("INSERT INTO email_verifications(user_id,token_hash,expires_at) VALUES(?,?,datetime('now','+24 hours'))")->execute([$userId,$hash]);
    send_plain_mail($email,'Verify your '.APP_NAME.' email',"Verify your email within 24 hours:\n\n".APP_URL.'/verify_email.php?token='.$raw);
}
/* The old magic-link functions were removed. Email capture now lives entirely in api/,
   ported from the proven Card Maker Messages system. */
function calc_items(array $desc,array $qty,array $price,float $taxRate): array {
    $items=[];$subtotal=0.0;
    foreach($desc as $i=>$d){$d=trim((string)$d); if($d==='') continue; $q=max(0,(float)($qty[$i]??1)); $p=max(0,(float)($price[$i]??0)); $line=$q*$p; $subtotal+=$line; $items[]=['description'=>$d,'qty'=>$q,'unit_price'=>$p,'line_total'=>$line];}
    $tax=$subtotal*max(0,$taxRate)/100; return [$items,$subtotal,$tax,$subtotal+$tax];
}
function unsubscribe_token(string $email): string {
    return substr(hash_hmac('sha256', strtolower($email), SITE_SALT ?: APP_URL), 0, 32);
}
function unsubscribe_url(string $email): string {
    return APP_URL.'/unsubscribe.php?e='.rawurlencode($email).'&u='.unsubscribe_token($email);
}
function mail_log(string $to,string $subject,string $transport,bool $ok,?string $error=null): void {
    try{ db()->prepare('INSERT INTO mail_log(to_addr,subject,transport,ok,error) VALUES(?,?,?,?,?)')
            ->execute([$to,$subject,$transport,$ok?1:0,$error]); }catch(Throwable $e){ /* logging must never break sending */ }
}
/* Sends a message and RECORDS the attempt, so a silent failure is impossible to miss.
   $copyAdmin adds ADMIN_EMAIL as a real recipient copy (used for subscriber events). */
function send_plain_mail(string $to,string $subject,string $body,bool $copyAdmin=false): bool {
    if(!$to){ mail_log((string)$to,$subject,'none',false,'No recipient address'); return false; }
    if(!MAIL_ENABLED){ mail_log($to,$subject,'disabled',false,'MAIL_ENABLED is false. Set it to true in secrets.php.'); return false; }
    if(!MAIL_FROM){ mail_log($to,$subject,'misconfigured',false,'MAIL_FROM is empty.'); return false; }
    // Every email carries an unsubscribe link, as the privacy page promises.
    if (stripos($body,'unsubscribe') === false) {
        $body .= "\n\n----\nTo stop receiving email from us and delete your record, open:\n".unsubscribe_url($to)."\n";
    }
    $err=null; $ok=false; $transport='mail()';
    if(SMTP_HOST && SMTP_USER && SMTP_PASS){
        $transport='smtp';
        $ok=smtp_mail($to,$subject,$body,$err);
    } else {
        $domain=parse_url(APP_URL,PHP_URL_HOST)?:'localhost';
        $headers='From: '.APP_NAME.' <'.MAIL_FROM.">\r\n".'Reply-To: '.MAIL_FROM."\r\n".'MIME-Version: 1.0'."\r\n".'Content-Type: text/plain; charset=UTF-8'."\r\n".'Content-Transfer-Encoding: 8bit'."\r\n".'Message-ID: <'.bin2hex(random_bytes(12)).'@'.$domain.">\r\n".'Date: '.date('r')."\r\n".'X-Mailer: '.APP_NAME;
        $ok=@mail($to,$subject,$body,$headers,'-f'.MAIL_FROM);
        if(!$ok) $err='PHP mail() returned false. No SMTP credentials are set, so delivery is unauthenticated and often rejected. Set SMTP_HOST, SMTP_USER and SMTP_PASS in secrets.php.';
    }
    mail_log($to,$subject,$transport,$ok,$err);
    // Copy the admin so there is a record in the inbox as well as the database.
    if($copyAdmin && ADMIN_EMAIL && strcasecmp(ADMIN_EMAIL,$to)!==0){
        $adminSubject='[copy] '.$subject;
        $adminBody="This is an admin copy of a message sent to ".$to.".\n\n----------\n\n".$body;
        $e2=null;
        if(SMTP_HOST && SMTP_USER && SMTP_PASS) smtp_mail(ADMIN_EMAIL,$adminSubject,$adminBody,$e2);
        else @mail(ADMIN_EMAIL,$adminSubject,$adminBody,'From: '.APP_NAME.' <'.MAIL_FROM.'>','-f'.MAIL_FROM);
        mail_log(ADMIN_EMAIL,$adminSubject,'admin-copy',true,$e2);
    }
    return $ok;
}
function smtp_mail(string $to,string $subject,string $body,?string &$err=null): bool {
    /* Ported verbatim from the Card Maker Messages mailer, which is proven in production on
       Hostinger shared hosting. Supports SMTP over SSL on 465 and STARTTLS on 587. */
    $host = SMTP_HOST ?: 'smtp.hostinger.com';
    $port = (int)(SMTP_PORT ?: 465);
    $user = (string)SMTP_USER;
    $pass = (string)SMTP_PASS;
    $from = MAIL_FROM ?: $user;
    $fromName = APP_NAME;
    if ($user === '' || $pass === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $err = 'SMTP credentials or recipient are missing.';
        return false;
    }
    $timeout = 20;
    $ssl = ($port === 465);
    $transport = $ssl ? "ssl://{$host}" : $host;
    $context = stream_context_create(['ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false,
        'SNI_enabled' => true,
        'peer_name' => $host,
    ]]);
    $socket = @stream_socket_client("{$transport}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) { $err = "SMTP connection failed: {$errstr} ({$errno})"; return false; }
    stream_set_timeout($socket, $timeout);

    $read = static function () use ($socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $send = static function (string $command) use ($socket): void { fwrite($socket, $command . "\r\n"); };
    $expect = static function (string $response, array $codes) use (&$err): bool {
        $code = substr($response, 0, 3);
        if (!in_array($code, $codes, true)) { $err = 'Unexpected SMTP response: ' . trim($response); return false; }
        return true;
    };

    $domain = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';
    if (!$expect($read(), ['220'])) { fclose($socket); return false; }
    $send('EHLO ' . $domain);
    if (!$expect($read(), ['250'])) { fclose($socket); return false; }

    if (!$ssl) {
        $send('STARTTLS');
        if (!$expect($read(), ['220'])) { fclose($socket); return false; }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $err = 'SMTP TLS negotiation failed.'; fclose($socket); return false;
        }
        $send('EHLO ' . $domain);
        if (!$expect($read(), ['250'])) { fclose($socket); return false; }
    }

    $send('AUTH LOGIN');
    if (!$expect($read(), ['334'])) { fclose($socket); return false; }
    $send(base64_encode($user));
    if (!$expect($read(), ['334'])) { fclose($socket); return false; }
    $send(base64_encode($pass));
    if (!$expect($read(), ['235'])) { fclose($socket); return false; }

    $send("MAIL FROM:<{$from}>");
    if (!$expect($read(), ['250'])) { fclose($socket); return false; }
    $send("RCPT TO:<{$to}>");
    if (!$expect($read(), ['250','251'])) { fclose($socket); return false; }
    $send('DATA');
    if (!$expect($read(), ['354'])) { fclose($socket); return false; }

    $safeSubject = str_replace(["\r","\n"], '', $subject);
    $messageId = sprintf('<%s@%s>', bin2hex(random_bytes(12)), $domain);
    $headers  = 'From: ' . mb_encode_mimeheader($fromName) . " <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= 'Subject: ' . mb_encode_mimeheader($safeSubject) . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";
    $headers .= "Message-ID: {$messageId}\r\n";
    $headers .= "X-Auto-Response-Suppress: All\r\n";
    $bodyOut = preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $body));
    $send($headers . "\r\n" . $bodyOut . "\r\n.");
    if (!$expect($read(), ['250'])) { fclose($socket); return false; }
    $send('QUIT');
    fclose($socket);
    return true;
}

function doc_registry(): array {
    return [
        'invoice'=>['slug'=>'invoice-template','label'=>'Invoice','title'=>'Invoice template: create and download in any currency','kw'=>'invoice template','h1'=>'Invoice template',
            'answer'=>'An invoice template is a ready-made layout for billing a customer. Add your business, the customer, line items, tax and a due date, then download a clean PDF. It works in any country and currency, carries no watermark, and your details stay in your browser.',
            'when'=>'Use an invoice when work is complete and payment is due. It is the formal request for payment and the record both sides keep.',
            'fields'=>['Your business name and address','Customer name and address','Unique invoice number','Issue date and due date','Line items with quantity, rate and tax','Subtotal, tax, discount and total','Payment terms and bank instructions'],
            'faqs'=>[['What should an invoice include?','A complete invoice includes your business details, the customer details, a unique invoice number, the issue and due dates, an itemised list of goods or services with quantities and prices, any tax and discount, the total amount due, and how to pay. Adding clear payment terms such as Net 14 helps you get paid on time.'],['Does this invoice generator cost anything?','No charge applies in the current release. You can build and download a professional invoice as a PDF with no watermark and no card details requested. There is no account to create: everything is built and stored in your own browser.'],['Do I need an invoice number?','Yes, every invoice should carry a unique sequential number. It lets you and your customer refer to the exact document, keeps your records in order, and is expected by most tax authorities. The builder suggests the next number automatically.'],['Can I use my own currency?','Yes. Choose your country and the currency, symbol and decimal format are set automatically, covering more than 150 currencies. You can also set a different currency per invoice for overseas customers.'],['How do I get paid faster?','Send the invoice promptly, set a clear due date, and state accepted payment methods plainly. Creating an account unlocks automatic polite reminders before and after the due date, which is the single biggest lever on late payment.']]],
        'quote'=>['slug'=>'quote-template','label'=>'Quote','title'=>'Quote template: set a fixed price and convert it','kw'=>'quote template','h1'=>'Quote template',
            'answer'=>'A quote template is a layout for telling a customer the fixed price of work before it starts. Add your line items and a valid-until date, then download a clean PDF. Accept it with one click and it converts straight into an invoice.',
            'when'=>'Use a quote to win the job. It sets a firm price and terms the customer can accept before any work begins.',
            'fields'=>['Your business details','Customer details','Quote number','Valid-until date','Itemised work with prices','Acceptance terms','Notes'],
            'faqs'=>[['What is the difference between a quote and an estimate?','A quote is a fixed price the customer can rely on once accepted. An estimate is an informed approximation that may change as the work becomes clearer. Use a quote when you can commit to a price, and an estimate when the scope is still uncertain.'],['How long should a quote be valid?','Most businesses set a valid-until date of 14 to 30 days. This protects you from rising costs and gently encourages the customer to decide. The builder includes a valid-until field so the date is always clear on the document.'],['Can I turn a quote into an invoice?','Yes. Once the customer accepts, one click converts the quote into an invoice with a new number, keeping the same customer and line items. The original quote stays unchanged as a record.'],['Does the quote generator cost anything?','No charge applies in the current release. You can build and download a quote with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a professional quote include?','Include your business and customer details, a quote number, the itemised work with clear prices, the total, a valid-until date, and any terms or exclusions. Being specific about what is and is not included prevents disputes later.']]],
        'estimate'=>['slug'=>'estimate-template','label'=>'Estimate','title'=>'Estimate template: give an approximate price','kw'=>'estimate template','h1'=>'Estimate template',
            'answer'=>'An estimate template is a layout for giving a customer an approximate price before work begins. It signals the likely cost while making clear the final figure may change. Fill in the fields and download a clean PDF, then convert it to an invoice when the job is agreed.',
            'when'=>'Use an estimate when the scope is not yet fixed and you want to give a realistic figure without committing to it.',
            'fields'=>['Your business details','Customer details','Estimate number','Valid-until date','Itemised work with approximate prices','A note that figures are estimated','Notes'],
            'faqs'=>[['Is an estimate legally binding?','An estimate is generally not a fixed commitment, unlike a quote. It is an informed approximation, so make clear on the document that the final price may vary. Stating this plainly protects you if costs change.'],['When should I use an estimate instead of a quote?','Use an estimate when the full scope is still unclear, for example before a site visit or when materials prices may move. Use a quote once you can commit to a firm figure.'],['Can I convert an estimate into an invoice?','Yes. When the customer agrees to proceed, convert the estimate into an invoice in one click. It carries over the customer and items and receives a new invoice number.'],['Does the estimate generator cost anything?','No charge applies in the current release. You can build and download a estimate with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['How accurate should an estimate be?','Base it on the information you have and add a sensible margin for the unknowns. It is better to estimate slightly high and come in under than to surprise the customer with a larger final bill.']]],
        'receipt'=>['slug'=>'receipt-template','label'=>'Receipt','title'=>'Receipt template: confirm a payment received','kw'=>'receipt template','h1'=>'Receipt template',
            'answer'=>'A receipt template is a layout confirming that a payment has been received. It records what was paid, when, and against which invoice. Fill in the details and download a clean PDF to give your customer proof of payment.',
            'when'=>'Use a receipt after money has been received, to give the customer proof and to close off the invoice in your records.',
            'fields'=>['Your business details','Customer details','Receipt number','Original invoice number','Payment date and method','Amount received','Any balance remaining'],
            'faqs'=>[['What is the difference between an invoice and a receipt?','An invoice requests payment before it is made. A receipt confirms payment after it has been made. You send an invoice to ask for money and a receipt to acknowledge you have received it.'],['Do I need to issue a receipt?','Many customers expect a receipt as proof of payment, and it is good practice for your own records. It is especially useful for cash payments where there is no bank record.'],['Can I create a receipt from an invoice?','Yes. Convert a paid invoice into a receipt in one click. It carries the original invoice number, the amount and the customer, and records the payment date.'],['Does the receipt generator cost anything?','No charge applies in the current release. You can build and download a receipt with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a receipt show?','A receipt should show your business details, the customer, a receipt number, the original invoice reference, the payment date and method, and the amount received. If part-paid, show the remaining balance clearly.']]],
        'purchase-order'=>['slug'=>'purchase-order-template','label'=>'Purchase order','title'=>'Purchase order template: order from a supplier','kw'=>'purchase order template','h1'=>'Purchase order template',
            'answer'=>'A purchase order template is a layout a buyer uses to order goods or services from a supplier. It lists what is being ordered, quantities, agreed prices and a delivery date, with a unique PO number. Fill in the fields and download a clean PDF.',
            'when'=>'Use a purchase order to formally place an order with a supplier and to authorise the spend inside your own business.',
            'fields'=>['Your business details as the buyer','Supplier details','Purchase order number','Order date and requested delivery date','Delivery address','Itemised goods or services with prices','Authorised-by name'],
            'faqs'=>[['What is a purchase order?','A purchase order is a document the buyer sends to a supplier to order goods or services at agreed prices. It becomes a binding contract once the supplier accepts it, and its PO number links the order to the later invoice.'],['How is a purchase order different from an invoice?','A purchase order is raised by the buyer to request goods, before delivery. An invoice is raised by the seller to request payment, after delivery. The invoice usually quotes the purchase order number.'],['Do small businesses need purchase orders?','Purchase orders help any business control spending and keep a clear record of what was ordered and agreed. They are especially useful once more than one person can commit money.'],['Does the purchase order generator cost anything?','No charge applies in the current release. You can build and download a purchase order with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a purchase order include?','Include the buyer and supplier details, a unique PO number, the order and requested delivery dates, the delivery address, an itemised list with agreed prices, the total, and who authorised the order.']]],
        'credit-note'=>['slug'=>'credit-note-template','label'=>'Credit note','title'=>'Credit note template: reduce an invoice already sent','kw'=>'credit note template','h1'=>'Credit note template',
            'answer'=>'A credit note template is a layout that reduces or cancels an amount a customer owes on a previous invoice. It records the original invoice number, the amount credited and the reason. Fill in the fields and download a clean PDF.',
            'when'=>'Use a credit note to correct an overcharge, handle a return, or apply a refund against an invoice you have already issued.',
            'fields'=>['Your business details','Customer details','Credit note number','Original invoice number','Reason for the credit','Itemised amounts being credited','Total credited'],
            'faqs'=>[['What is a credit note?','A credit note is a document that cancels all or part of a previously issued invoice. It is used for returns, overcharges or corrections, and it must reference the original invoice so both sides can reconcile their records.'],['When do I issue a credit note instead of deleting an invoice?','Once an invoice has been sent, you should not delete it. Issue a credit note to reverse it, so there is a clear audit trail of what changed and why. This keeps your records and your customer’s in step.'],['Does a credit note mean a cash refund?','Not necessarily. A credit note reduces what the customer owes. It can be set against future invoices or, if the customer has already paid, followed by an actual refund through your own payment channel.'],['Does the credit note generator cost anything?','No charge applies in the current release. You can build and download a credit note with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What must a credit note include?','A credit note must show your business and customer details, a credit note number, the original invoice number it relates to, the reason for the credit, the itemised amounts, and the total being credited.']]],
        'proforma-invoice'=>['slug'=>'proforma-invoice-template','label'=>'Proforma invoice','title'=>'Proforma invoice template: bill before you supply','kw'=>'proforma invoice template','h1'=>'Proforma invoice template',
            'answer'=>'A proforma invoice template is a layout for a preliminary bill sent before goods or services are supplied. It shows the expected charges so the customer can arrange payment or approval, but it is not a tax invoice. Fill in the fields and download a clean PDF.',
            'when'=>'Use a proforma invoice to confirm price and terms before delivery, often for prepayment, customs, or internal approval.',
            'fields'=>['Your business details','Customer details','Proforma number','Valid-until date','Itemised goods or services with prices','A clear "not a tax invoice" notice','Payment or delivery terms'],
            'faqs'=>[['What is a proforma invoice?','A proforma invoice is a preliminary bill issued before the goods or services are delivered. It lets the customer see the expected cost and arrange payment or approval, but it does not record a sale and is not used to reclaim tax.'],['Is a proforma invoice a real invoice?','No. It is an estimate of what the final invoice will contain, clearly marked as not a tax invoice. You issue the actual tax invoice once the goods or services are supplied.'],['When are proforma invoices used?','They are common where payment is needed up front, for international shipments and customs, or where a customer needs a document to approve the spend before committing.'],['Does the proforma generator cost anything?','No charge applies in the current release. You can build and download a proforma with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['Can a proforma become a real invoice?','Yes. Once you deliver, convert the proforma into a full invoice in one click. It keeps the customer and line items and receives a proper invoice number.']]],
        'delivery-note'=>['slug'=>'delivery-note-template','label'=>'Delivery note','title'=>'Delivery note template: send goods with a signed record','kw'=>'delivery note template','h1'=>'Delivery note template',
            'answer'=>'A delivery note template is a layout listing the goods included in a shipment, sent with the delivery so the recipient can check what arrived. It shows quantities delivered and a space to sign on receipt. Fill in the fields and download a clean PDF.',
            'when'=>'Use a delivery note when sending goods, so the recipient can confirm what was delivered against what was ordered.',
            'fields'=>['Your business details','Customer and delivery address','Delivery note number','Delivery date','Related order or invoice number','Items and quantities delivered','Received-by signature line'],
            'faqs'=>[['What is a delivery note?','A delivery note is a document that travels with a shipment and lists the goods inside it. The recipient checks the goods against the note and signs to confirm receipt, which helps resolve any disputes about what arrived.'],['Is a delivery note the same as an invoice?','No. A delivery note lists goods delivered and does not show prices or request payment. The invoice, which does request payment, is usually sent separately.'],['Why include a signature line?','A signed delivery note is proof the customer received the goods in the stated quantities. It protects you if the customer later queries whether a delivery arrived.'],['Does the delivery note generator cost anything?','No charge applies in the current release. You can build and download a delivery note with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a delivery note show?','Show your business details, the customer and delivery address, a delivery note number, the delivery date, the related order or invoice reference, the items and quantities delivered, and a line for the recipient to sign.']]],
        'tax-invoice'=>['slug'=>'tax-invoice-template','label'=>'Tax invoice','title'=>'Tax invoice template: show tax and your registration number','kw'=>'tax invoice template','h1'=>'Tax invoice template',
            'answer'=>'A tax invoice template is a layout for an invoice that meets tax requirements, showing your tax registration number and a clear breakdown of tax charged. Fill in the fields, add your tax number, and download a clean PDF.',
            'when'=>'Use a tax invoice when you are registered for VAT, GST or sales tax and must show the tax charged so the customer can reclaim it.',
            'fields'=>['Your business details and tax registration number','Customer details','Tax invoice number','Issue date','Itemised goods or services','Tax rate and tax amount shown separately','Subtotal, tax and total'],
            'faqs'=>[['What makes an invoice a tax invoice?','A tax invoice shows the seller’s tax registration number and sets out the tax charged separately from the net amount, so the customer can reclaim it where allowed. The exact wording required varies by country.'],['Do I need to be tax registered to issue one?','Yes. Only a business registered for VAT, GST or an equivalent should issue a tax invoice, because it must display a valid tax registration number. If you are not registered, issue a standard invoice.'],['What tax details must appear?','Your tax registration number, the tax rate applied, the tax amount as a separate line, and the net and gross totals. The builder checks that the tax registration field is completed before you download.'],['Does the tax invoice generator cost anything?','No charge applies in the current release. You can build and download a tax invoice with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['Can I show more than one tax rate?','Yes. The advanced builder supports named taxes and per-line tax rates, so you can apply different rates to different items and show a clear tax breakdown.']]],
        'statement'=>['slug'=>'statement-template','label'=>'Client statement','title'=>'Statement of account template: summarise a customer account','kw'=>'statement of account template','h1'=>'Statement of account template',
            'answer'=>'A statement of account template summarises all invoices, payments and the balance a customer owes over a period. It gives the customer a single view of their account. Fill in the fields and download a clean PDF.',
            'when'=>'Use a statement to remind a customer of their overall balance, especially when several invoices are outstanding.',
            'fields'=>['Your business details','Customer details','Statement date and period','List of invoices with dates and amounts','Payments received and running balance','Opening balance, charges, payments and closing balance'],
            'faqs'=>[['What is a statement of account?','A statement of account is a summary sent to a customer listing their invoices, payments and outstanding balance over a period. It helps the customer see everything they owe in one place rather than invoice by invoice.'],['How is a statement different from an invoice?','An invoice bills for a single set of goods or services. A statement summarises many invoices and payments to show the overall balance. A statement does not replace the individual invoices.'],['How often should I send statements?','Monthly is common, particularly for customers with regular or multiple invoices. Sending a statement is a gentle, professional way to prompt payment of anything overdue.'],['Does the statement generator cost anything?','No charge applies in the current release. You can build and download a statement with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a statement include?','Include your business and customer details, the statement date and period, a list of invoices with their dates and amounts, payments received, and the opening and closing balance.']]],
        'commercial-invoice'=>['slug'=>'commercial-invoice-template','label'=>'Commercial invoice','title'=>'Commercial invoice template: declare goods for customs','kw'=>'commercial invoice template','h1'=>'Commercial invoice template',
            'answer'=>'A commercial invoice template is a layout used for international shipments. It declares the goods, their value and the parties involved so customs can assess duties and taxes. Fill in the fields and download a clean PDF.',
            'when'=>'Use a commercial invoice when shipping goods across borders, as customs authorities require it to clear and value the shipment.',
            'fields'=>['Exporter and importer details','Commercial invoice number','Country of origin and destination','Itemised goods with values and HS codes','Currency and total value','Shipping and incoterms','Declaration and signature'],
            'faqs'=>[['What is a commercial invoice?','A commercial invoice is a customs document used in international trade. It declares the goods being shipped, their value, and the exporter and importer, so customs can assess duties and taxes and clear the shipment.'],['How is it different from a normal invoice?','A commercial invoice adds trade-specific details such as country of origin, HS codes and incoterms that customs need. A standard invoice simply requests payment and does not carry those fields.'],['Do I need a commercial invoice for every export?','Most cross-border shipments of goods require one. Check the requirements of the destination country, as some also need a packing list and certificate of origin.'],['Does the commercial invoice generator cost anything?','No charge applies in the current release. You can build and download a commercial invoice with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What details must a commercial invoice include?','Include the exporter and importer, an invoice number, country of origin and destination, an itemised list of goods with values and HS codes, the currency and total, the shipping terms, and a signed declaration.']]],
        'debit-note'=>['slug'=>'debit-note-template','label'=>'Debit note','title'=>'Debit note template: increase an invoice already sent','kw'=>'debit note template','h1'=>'Debit note template',
            'answer'=>'A debit note template is a layout that increases the amount a customer owes on a previous invoice, for example after an undercharge or additional goods. It references the original invoice and states the extra amount. Fill in the fields and download a clean PDF.',
            'when'=>'Use a debit note to raise the amount owed after an invoice was issued, such as an undercharge, price adjustment or extra items.',
            'fields'=>['Your business details','Customer details','Debit note number','Original invoice number','Reason for the additional charge','Itemised additional amounts','Total additional amount'],
            'faqs'=>[['What is a debit note?','A debit note is a document that increases the amount a customer owes on a previously issued invoice. It is used when an invoice was undercharged, or when extra goods or services were added after the original invoice.'],['How is a debit note different from a credit note?','A debit note increases what the customer owes; a credit note reduces it. A debit note is the correct document for an undercharge, and a credit note for an overcharge or return.'],['When do I issue a debit note?','Issue a debit note when you need to charge more than the original invoice, rather than editing the invoice after it has been sent, so there is a clear audit trail.'],['Does the debit note generator cost anything?','No charge applies in the current release. You can build and download a debit note with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a debit note include?','Include your business and customer details, a debit note number, the original invoice number, the reason for the extra charge, the itemised additional amounts, and the total.']]],
        'progress-invoice'=>['slug'=>'progress-invoice-template','label'=>'Progress invoice','title'=>'Progress invoice template: bill a completed stage','kw'=>'progress invoice template','h1'=>'Progress invoice template',
            'answer'=>'A progress invoice template lets you bill part way through a project, for a stage or an agreed percentage of the work completed, rather than waiting until the end. It shows what has already been invoiced and what remains, which keeps cash flow steady on longer jobs. Fill in the fields and download a clean PDF.',
            'when'=>'Use a progress invoice on longer jobs to bill for completed stages as you go, which steadies cash flow instead of waiting until the end.',
            'fields'=>['Your business details','Customer details','Progress invoice number','Project or contract reference','Stage or percentage completed','Amount for this stage','Amount previously invoiced and remaining'],
            'faqs'=>[['What is a progress invoice?','A progress invoice bills a customer for part of a project as it is completed, rather than the whole amount at the end. It is common in construction, consultancy and other stage-based work.'],['How does progress invoicing work?','You agree the total, then invoice for each stage or an agreed percentage as it is finished. Each progress invoice shows what has been billed so far and what remains.'],['Why use progress invoices?','They keep cash flow steady on long jobs and reduce the risk of a large unpaid balance at the end. They also give the customer a clear view of spend as the work advances.'],['Does the progress invoice generator cost anything?','No charge applies in the current release. You can build and download a progress invoice with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a progress invoice include?','Include your business and customer details, a progress invoice number, the project reference, the stage or percentage completed, the amount for this stage, and the amounts previously invoiced and remaining.']]],
        'final-invoice'=>['slug'=>'final-invoice-template','label'=>'Final invoice','title'=>'Final invoice template: collect the closing balance','kw'=>'final invoice template','h1'=>'Final invoice template',
            'answer'=>'A final invoice template bills the remaining balance at the end of a job, after any deposit or progress invoices. It shows the total, what was already paid, and the balance due. Fill in the fields and download a clean PDF.',
            'when'=>'Use a final invoice to close out a job, collecting the last balance after deposits or progress payments.',
            'fields'=>['Your business details','Customer details','Final invoice number','Project or contract reference','Total contract value','Deposits and progress payments received','Final balance due'],
            'faqs'=>[['What is a final invoice?','A final invoice is issued at the end of a project to bill the remaining balance after any deposit or progress invoices. It reconciles the total against what has already been paid.'],['How is a final invoice different from a normal invoice?','A final invoice explicitly accounts for earlier payments on the same job and shows only the outstanding balance, whereas a standard invoice bills a full amount on its own.'],['When should I send a final invoice?','Send it once the work is complete and accepted, so the customer can settle the remaining balance. It also serves as the closing record for the job.'],['Does the final invoice generator cost anything?','No charge applies in the current release. You can build and download a final invoice with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a final invoice include?','Include your business and customer details, a final invoice number, the project reference, the total contract value, all deposits and progress payments received, and the final balance due.']]],
        'deposit-invoice'=>['slug'=>'deposit-invoice-template','label'=>'Deposit invoice','title'=>'Deposit invoice template: request payment up front','kw'=>'deposit invoice template','h1'=>'Deposit invoice template',
            'answer'=>'A deposit invoice template requests an upfront payment before work begins. It records the deposit amount and how it applies to the final total. Fill in the fields and download a clean PDF. This tool does not process payments.',
            'when'=>'Use a deposit invoice to request money up front to secure a booking or cover initial costs before starting the work.',
            'fields'=>['Your business details','Customer details','Deposit invoice number','Project or order reference','Deposit amount requested','How the deposit applies to the total','Payment terms'],
            'faqs'=>[['What is a deposit invoice?','A deposit invoice requests an upfront payment before work starts. It records the deposit amount and notes that it will be deducted from the final invoice for the job.'],['Why request a deposit?','A deposit secures the booking, covers early materials or costs, and shows the customer is committed. It reduces the risk of doing work that is never paid for.'],['Does this tool take the deposit payment?','No. The deposit invoice states the amount requested; payment is arranged directly between you and the customer through your own channel. This tool does not process payments.'],['Does the deposit invoice generator cost anything?','No charge applies in the current release. You can build and download a deposit invoice with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a deposit invoice include?','Include your business and customer details, a deposit invoice number, the project reference, the deposit amount, a note on how it applies to the total, and the payment terms.']]],
        'sales-order'=>['slug'=>'sales-order-template','label'=>'Sales order','title'=>'Sales order template: confirm an accepted order','kw'=>'sales order template','h1'=>'Sales order template',
            'answer'=>'A sales order template confirms a customer order that you have accepted, sitting between a quote and an invoice. It lists the goods or services, agreed prices and delivery details. Fill in the fields and download a clean PDF.',
            'when'=>'Use a sales order to confirm what the customer has ordered and agreed, before you deliver and invoice.',
            'fields'=>['Your business details','Customer details','Sales order number','Order date and requested delivery date','Itemised goods or services with prices','Delivery address','Agreed terms'],
            'faqs'=>[['What is a sales order?','A sales order is a document you create to confirm a customer order you have accepted. It sits between the quote the customer accepted and the invoice you later send, and records exactly what was agreed.'],['How is a sales order different from a purchase order?','A purchase order is raised by the buyer to place an order. A sales order is raised by the seller to confirm that order internally and to the customer.'],['Do I need a sales order?','They help product and service businesses track what has been agreed and what needs to be delivered, especially when there is a gap between the order and the invoice.'],['Does the sales order generator cost anything?','No charge applies in the current release. You can build and download a sales order with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a sales order include?','Include your business and customer details, a sales order number, the order and requested delivery dates, an itemised list with agreed prices, the delivery address, and the agreed terms.']]],
        'work-order'=>['slug'=>'work-order-template','label'=>'Work order','title'=>'Work order template: instruct and record a job','kw'=>'work order template','h1'=>'Work order template',
            'answer'=>'A work order template, or job sheet, sets out the work to be carried out, for a tradesperson or field team. It lists the tasks, materials, location and who is assigned. Fill in the fields and download a clean PDF.',
            'when'=>'Use a work order to instruct and record a job for cleaners, plumbers, electricians, contractors and maintenance teams.',
            'fields'=>['Your business details','Customer and site details','Work order number','Scheduled date and time','Tasks to be carried out','Materials and labour','Assigned to and sign-off'],
            'faqs'=>[['What is a work order?','A work order, or job sheet, is a document that describes the work to be done, where, and by whom. Trades and maintenance businesses use it to instruct staff and record what was carried out.'],['How is a work order different from an invoice?','A work order authorises and records the work; an invoice requests payment for it. The work order often becomes the basis for the invoice once the job is complete.'],['Who uses work orders?','Cleaners, plumbers, electricians, contractors, and maintenance and facilities teams use them to schedule jobs, list materials and get sign-off from the customer.'],['Does the work order generator cost anything?','No charge applies in the current release. You can build and download a work order with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a work order include?','Include your business and customer or site details, a work order number, the scheduled date, the tasks, the materials and labour, who is assigned, and a space for sign-off.']]],
        'timesheet'=>['slug'=>'timesheet-template','label'=>'Timesheet','title'=>'Timesheet template: record hours ready to bill','kw'=>'timesheet template','h1'=>'Timesheet template',
            'answer'=>'A timesheet template records the hours worked on tasks or projects, so you can bill accurately or run payroll. Enter dates, hours and rates and download a clean PDF. Convert billable hours into an invoice in one step.',
            'when'=>'Use a timesheet to record billable hours for clients or to track staff time before invoicing or paying.',
            'fields'=>['Your business or worker details','Client or project','Week or period','Dates, tasks and hours','Hourly rate','Total hours and value','Approval'],
            'faqs'=>[['What is a timesheet?','A timesheet records the hours worked on tasks or projects over a period. Freelancers use it to bill clients accurately, and businesses use it to track staff time for payroll or project costing.'],['How do I turn a timesheet into an invoice?','List the billable hours and rate on the timesheet, then carry the total into an invoice. In this tool you can record hours and generate an invoice from them without re-entering the detail.'],['Should a timesheet be approved?','For client work and payroll, an approval or sign-off line adds accountability and reduces disputes about the hours claimed.'],['Does the timesheet generator cost anything?','No charge applies in the current release. You can build and download a timesheet with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a timesheet include?','Include the worker or business, the client or project, the period, the dates with tasks and hours, the hourly rate, the total hours and value, and an approval line.']]],
        'expense-report'=>['slug'=>'expense-report-template','label'=>'Expense report','title'=>'Expense report template: claim or recharge costs','kw'=>'expense report template','h1'=>'Expense report template',
            'answer'=>'An expense report template lists business or project costs for reimbursement or record keeping, with the date, category, description and amount of each item. It gives you a clear total to claim or to recharge to a client, and a place to reference receipts. Fill in the fields and download a clean PDF.',
            'when'=>'Use an expense report to claim reimbursable costs or to record project expenses that you will pass on to a client.',
            'fields'=>['Your details or employee details','Report period','Date, category and description of each expense','Amount and any tax','Total claimed','Receipts reference','Approval'],
            'faqs'=>[['What is an expense report?','An expense report is a document listing business or project costs, with the date, category and amount of each. It is used to claim reimbursement or to record expenses for accounting.'],['Can I bill expenses to a client?','Yes. Reimbursable project expenses can be carried into an invoice as line items, so the client is charged for costs you incurred on their behalf.'],['What counts as a business expense?','Costs incurred wholly for the business or project, such as travel, materials or subsistence. Keep receipts, as they support the claim and any tax treatment.'],['Does the expense report generator cost anything?','No charge applies in the current release. You can build and download a expense report with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should an expense report include?','Include the person or business, the period, each expense with its date, category, description and amount, any tax, the total, a reference to receipts, and an approval line.']]],
        'packing-slip'=>['slug'=>'packing-slip-template','label'=>'Packing slip','title'=>'Packing slip template: list what is in the parcel','kw'=>'packing slip template','h1'=>'Packing slip template',
            'answer'=>'A packing slip template lists the items inside a shipment, normally without prices, so the recipient can check the contents against their order. It travels with the parcel and helps warehouse and delivery staff handle it without exposing your pricing. Fill in the fields and download a clean PDF.',
            'when'=>'Use a packing slip inside or on a parcel so the recipient can verify the contents against their order.',
            'fields'=>['Your business details','Customer and delivery address','Packing slip number','Related order or invoice number','Items and quantities packed','Any items on back-order','Packed-by reference'],
            'faqs'=>[['What is a packing slip?','A packing slip is a document included with a shipment that lists the items inside, usually without prices. The recipient checks the contents against their order and against the packing slip.'],['How is a packing slip different from an invoice?','A packing slip lists what was physically shipped and normally omits prices. An invoice requests payment and shows amounts. They are often sent separately.'],['Why omit prices on a packing slip?','Prices are usually left off so the document can be seen by warehouse and delivery staff, and by the recipient, without exposing commercial pricing.'],['Does the packing slip generator cost anything?','No charge applies in the current release. You can build and download a packing slip with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a packing slip include?','Include your business details, the customer and delivery address, a packing slip number, the related order or invoice number, the items and quantities packed, any back-ordered items, and a packed-by reference.']]],
        'payment-reminder'=>['slug'=>'payment-reminder-template','label'=>'Payment reminder','title'=>'Payment reminder template: chase an overdue invoice politely','kw'=>'payment reminder template','h1'=>'Payment reminder template',
            'answer'=>'A payment reminder template is a polite notice that an invoice is due or overdue. It restates the invoice number, amount and due date and asks for payment. Fill in the fields and download a clean PDF, or create an account to send reminders automatically.',
            'when'=>'Use a payment reminder to chase an unpaid invoice without straining the relationship, before and after the due date.',
            'fields'=>['Your business details','Customer details','Invoice number being chased','Original amount and due date','Days overdue','Polite request for payment','Payment instructions'],
            'faqs'=>[['What is a payment reminder?','A payment reminder is a short, polite notice that an invoice is due or overdue. It restates the invoice number, the amount and the due date, and asks the customer to pay.'],['When should I send a payment reminder?','A friendly reminder a few days before the due date, then again shortly after, works well. Most late payment is simply forgetfulness, so an early nudge is often enough.'],['How do I keep reminders polite?','Lead with a friendly tone, assume the best, and make paying easy by restating the amount and how to pay. Escalate the firmness gradually only if it stays unpaid.'],['Can this tool send reminders automatically?','Create an account to schedule polite reminders before and after the due date, with a one-click cancel if the customer has already paid. Chasing overdue invoices is a major small-business pain point, and automating it saves hours.'],['Does the payment reminder generator cost anything?','No charge applies in the current release. You can build and download a payment reminder with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.']]],
        'account-balance-letter'=>['slug'=>'account-balance-letter-template','label'=>'Account balance letter','title'=>'Account balance letter template: state one balance owed','kw'=>'account balance letter template','h1'=>'Account balance letter template',
            'answer'=>'An account balance letter template is a short formal document telling a customer the total they currently owe as at a given date. It is simpler than a full statement because it gives one clear figure rather than every transaction. Fill in the fields and download a clean PDF in any currency, with no watermark.',
            'when'=>'Use an account balance letter to confirm to a customer, in one clear figure, what they owe as at a given date.',
            'fields'=>['Your business details','Customer details','Letter date','As-at date','Current balance owed','Brief summary of what it covers','Payment instructions'],
            'faqs'=>[['What is an account balance letter?','An account balance letter is a brief formal document that tells a customer the total amount they owe as at a certain date. It is simpler than a full statement of account.'],['How is it different from a statement?','A statement lists every invoice and payment. An account balance letter gives the single current figure, which is useful for a quick confirmation or a credit reference.'],['When would I use one?','Use it to confirm a balance for a customer, a lender or a reference, or as a gentle prompt showing exactly what is outstanding.'],['Does the account balance letter generator cost anything?','No charge applies in the current release. You can build and download a account balance letter with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should it include?','Include your business and customer details, the letter date, the as-at date, the current balance owed, a brief note of what it covers, and payment instructions.']]],
        'completion-certificate'=>['slug'=>'completion-certificate-template','label'=>'Completion certificate','title'=>'Completion certificate template: get the work signed off','kw'=>'completion certificate template','h1'=>'Completion certificate template',
            'answer'=>'A completion certificate template lets a customer confirm in writing that work has been finished to their satisfaction. It records the job, the site, the date completed and a signature, which supports your final invoice and settles any later question about whether the work was accepted. Fill in the fields and download a clean PDF.',
            'when'=>'Use a completion certificate to have the customer confirm the work is done, which supports your final invoice.',
            'fields'=>['Your business details','Customer and site details','Certificate number','Description of work completed','Date completed','Customer sign-off','Any notes or warranty'],
            'faqs'=>[['What is a service completion certificate?','A service completion certificate is a document a customer signs to confirm that the work has been completed to their satisfaction. It is common in trades and service businesses.'],['Why use a completion certificate?','It gives you written confirmation that the job is finished and accepted, which supports your final invoice and reduces later disputes about the work.'],['When should the customer sign it?','At the end of the job, once they have seen the completed work. The signed certificate then becomes part of your record for that job.'],['Does the completion certificate generator cost anything?','No charge applies in the current release. You can build and download a completion certificate with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should it include?','Include your business and customer or site details, a certificate number, a description of the work completed, the completion date, a customer sign-off, and any notes or warranty terms.']]],
        'goods-received-note'=>['slug'=>'goods-received-note-template','label'=>'Goods received note','title'=>'Goods received note template: record what actually arrived','kw'=>'goods received note template','h1'=>'Goods received note template',
            'answer'=>'A goods received note template, often shortened to GRN, records the goods your business has received against a purchase order. It confirms the quantities and condition of what actually arrived, so you can check the delivery against what was ordered and what the supplier later invoices. Fill in the fields and download a clean PDF.',
            'when'=>'Use a goods received note to record and check a delivery against the purchase order that ordered it.',
            'fields'=>['Your business details','Supplier details','GRN number','Related purchase order number','Date received','Items and quantities received','Condition and any discrepancies'],
            'faqs'=>[['What is a goods received note?','A goods received note, or GRN, is a document that records the goods a business has received against a purchase order. It confirms the quantities and condition of what actually arrived.'],['Why use a GRN?','It provides a check between what was ordered, what was delivered, and what is later invoiced by the supplier, which helps catch shortages and errors.'],['How does a GRN relate to a purchase order?','The purchase order sets out what was ordered; the GRN records what was received. Comparing the two, and the supplier invoice, is a standard control.'],['Does the goods received note generator cost anything?','No charge applies in the current release. You can build and download a goods received note with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a GRN include?','Include your business and supplier details, a GRN number, the related purchase order number, the date received, the items and quantities received, and a note of condition and any discrepancies.']]],
        'return-note'=>['slug'=>'return-note-template','label'=>'Return note','title'=>'Return note template: record returned goods','kw'=>'return note template','h1'=>'Return note template',
            'answer'=>'A return note template records goods a customer is returning, or a refund that has been agreed, with the reason and the items involved. It usually precedes a credit note. Fill in the fields and download a clean PDF.',
            'when'=>'Use a return note to document returned goods or an agreed refund before issuing a credit note.',
            'fields'=>['Your business details','Customer details','Return note number','Original invoice number','Reason for return or refund','Items and quantities returned','Value and next step'],
            'faqs'=>[['What is a return note?','A return note records goods a customer is returning, or a refund that has been agreed, with the reason and the items involved. It is often the step before a credit note is issued.'],['How does a return note relate to a credit note?','The return note documents the return itself; the credit note then reduces what the customer owes or records the refund. Using both keeps a clear trail.'],['Do I need a return note?','It helps both sides agree what is being returned and why, which reduces disputes and supports the credit note or refund that follows.'],['Does the return note generator cost anything?','No charge applies in the current release. You can build and download a return note with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a return note include?','Include your business and customer details, a return note number, the original invoice number, the reason for the return or refund, the items and quantities returned, and the value with the next step.']]],
        'delivery-challan'=>['slug'=>'delivery-challan-template','label'=>'Delivery challan','title'=>'Delivery challan format: send goods without an invoice','kw'=>'delivery challan format','h1'=>'Delivery challan template',
            'answer'=>'A delivery challan is a document that travels with goods being moved without a sale, or before the invoice is raised. It lists what is being sent and how much, so the receiver can check the consignment on arrival. It records quantities rather than prices. Fill in the fields and download a clean PDF in any currency, with no watermark.',
            'when'=>'Use a delivery challan when goods leave your premises but no invoice is being raised yet, such as stock transfers, goods sent for job work, items on approval, or samples.',
            'fields'=>['Consignor and consignee details','Challan number and date','Place of dispatch and delivery','Description of goods with quantity and unit','Reason for transfer','Vehicle or carrier reference','Receiver signature'],
            'faqs'=>[['What is a delivery challan?','A delivery challan is a document that accompanies goods in transit when no invoice is raised at that moment. It proves what was sent and in what quantity, and lets the receiver check the consignment against it on arrival.'],['How is a delivery challan different from an invoice?','An invoice demands payment and shows prices. A delivery challan records movement of goods and normally shows quantities only. The invoice may follow later, once the goods are accepted or the job is complete.'],['When do you use a delivery challan instead of a delivery note?','The two overlap. Delivery challan is the term used most widely in India and South Asia, particularly for stock transfers and goods sent for job work. Delivery note is the more common term in the UK and Europe. Use whichever your customer expects.'],['Does a delivery challan need prices?','Usually not. Because no sale is taking place at that point, most delivery challans show description, quantity and unit only. This template leaves prices off by default.'],['How many copies of a delivery challan are needed?','Commonly three: one stays with the consignor, one travels with the goods, and one is signed by the receiver and returned. Download the PDF and print the copies you need.']]],
    'remittance-advice'=>['slug'=>'remittance-advice-template','label'=>'Remittance advice','title'=>'Remittance advice template: tell a supplier what you have paid','kw'=>'remittance advice template','h1'=>'Remittance advice template',
            'answer'=>'A remittance advice is a note sent to a supplier confirming which invoices a payment covers. It matters because a single bank transfer often settles several invoices at once, and without it the supplier has to guess how to allocate the money. Fill in the fields and download a clean PDF in any currency, with no watermark.',
            'when'=>'Send a remittance advice whenever you pay a supplier, especially when one payment settles several invoices or a partial amount.',
            'fields'=>['Your business details','Supplier details','Payment date and reference','Each invoice number and date','Invoice amount and amount paid against it','Total remitted','Payment method'],
            'faqs'=>[['What is a remittance advice?','A remittance advice is a document sent by a payer to a supplier, listing the invoices a payment covers. It lets the supplier allocate the money to the right invoices instead of guessing.'],['Is a remittance advice a legal requirement?','No. It is a courtesy, but a valuable one. Suppliers who receive remittance advice notes reconcile faster and are far less likely to chase you for invoices you have already paid.'],['What is the difference between a remittance advice and a receipt?','A remittance advice is sent by the payer to say what they have paid. A receipt is issued by the recipient to confirm the money arrived. They travel in opposite directions.'],['Can one remittance advice cover several invoices?','Yes, and that is the main reason it exists. List each invoice on its own line with the amount paid against it, so a single transfer can be allocated correctly.'],['What if I am only paying part of an invoice?','Show the full invoice amount and the amount paid against it on the same line. The difference tells the supplier what remains outstanding without a separate conversation.']]],
    'consignment-note'=>['slug'=>'consignment-note-template','label'=>'Consignment note','title'=>'Consignment note template: paperwork that travels with freight','kw'=>'consignment note template','h1'=>'Consignment note template',
            'answer'=>'A consignment note is the document that travels with freight, naming the sender, the receiver, the carrier and the goods being carried. It is the carrier\'s record of what was accepted for transport and the receiver\'s proof of what arrived. Fill in the fields and download a clean PDF, with no watermark.',
            'when'=>'Use a consignment note whenever goods are handed to a carrier, so the sender, carrier and receiver all hold the same record of the shipment.',
            'fields'=>['Sender and receiver details','Consignment note number and date','Origin and destination','Carrier name','Description of goods','Number of packages, weight and dimensions','Receiver signature on delivery'],
            'faqs'=>[['What is a consignment note?','A consignment note is a transport document that accompanies goods handed to a carrier. It identifies the sender, receiver, carrier and the goods, and is signed on delivery as proof of receipt.'],['What is the difference between a consignment note and a waybill?','They are close relatives and the terms are often used interchangeably. In practice a consignment note tends to be the contract of carriage between sender and carrier, while a waybill is more often the carrier\'s own tracking document.'],['Is a consignment note proof of ownership?','No. It is evidence of carriage, not title. It shows goods were handed over, carried and delivered. A bill of lading is the document that can transfer ownership.'],['How many copies are needed?','Usually three: one for the sender, one for the carrier, and one that travels with the goods and is signed by the receiver.'],['Does a consignment note show prices?','Normally not. It records what is being carried rather than what it is worth, so this template shows description, packages, weight and dimensions instead of prices.']]],
    'payment-voucher'=>['slug'=>'payment-voucher-template','label'=>'Payment voucher','title'=>'Payment voucher template: authorise and record a payment','kw'=>'payment voucher template','h1'=>'Payment voucher template',
            'answer'=>'A payment voucher is an internal document that authorises a payment and records what it was for, who approved it and who received it. It is the paper trail behind money leaving a business. Fill in the fields and download a clean PDF in any currency, with no watermark.',
            'when'=>'Use a payment voucher to authorise and record a payment out of the business, so every payment has a documented reason and an approval signature.',
            'fields'=>['Your business details','Who the payment is to','Voucher number and date','Particulars of what is being paid for','Account or cost code','Amount in figures','Prepared by, approved by and received by signatures'],
            'faqs'=>[['What is a payment voucher?','A payment voucher is an internal control document recording that a payment was authorised, what it was for, and who approved it. It supports the entry in your books and gives auditors a trail to follow.'],['How is a payment voucher different from a receipt?','A payment voucher is created by the payer before or as money leaves. A receipt is issued by the person receiving the money afterwards. One authorises, the other confirms.'],['Who signs a payment voucher?','Typically three people: whoever prepared it, whoever approved it, and whoever received the money. Separating those roles is what makes it a control rather than a formality.'],['Do I need a payment voucher for small payments?','Many businesses use a petty cash voucher for small amounts and a payment voucher above a set threshold. Choose a threshold and apply it consistently.'],['Does a payment voucher replace an invoice?','No. The supplier\'s invoice is the evidence of what you owe. The payment voucher is your internal authorisation to pay it. Keep both together.']]],
    'cash-receipt'=>['slug'=>'cash-receipt-template','label'=>'Cash receipt','title'=>'Cash receipt template: confirm cash received on the spot','kw'=>'cash receipt template','h1'=>'Cash receipt template',
            'answer'=>'A cash receipt confirms that money has been received, usually in person and at the moment of payment. It protects both sides: the payer has proof they paid, and the business has a record of what came in. Fill in the fields and download a clean PDF in any currency, with no watermark.',
            'when'=>'Give a cash receipt whenever you take payment in cash or on the spot, so both sides hold the same record of the amount and the date.',
            'fields'=>['Your business details','Who paid','Receipt number','Date payment was received','What the payment was for','Amount received','Payment method'],
            'faqs'=>[['What is a cash receipt?','A cash receipt is a document confirming money has been received. It states who paid, how much, when and what for, and is normally handed over at the moment of payment.'],['What is the difference between a cash receipt and an invoice?','An invoice asks for payment. A cash receipt confirms payment has already been made. The invoice comes first, the receipt afterwards.'],['Do I have to give a receipt for cash?','In many places a customer is entitled to a receipt on request, and for cash it is a sensible habit regardless. Cash leaves no bank record, so the receipt is often the only evidence the payment happened.'],['Should the receipt show the payment method?','Yes. Recording whether it was cash, card or transfer makes reconciliation far easier later, and distinguishes this receipt from others at a glance.'],['Can I use this for card or bank payments?','Yes. Change the payment method field. The document works for any payment received, though the term cash receipt is most often used for payment taken in person.']]],
    'rent-invoice'=>['slug'=>'rent-invoice-template','label'=>'Rent invoice','title'=>'Rent invoice template: bill a tenant for a rental period','kw'=>'rent invoice template','h1'=>'Rent invoice template',
            'answer'=>'A rent invoice bills a tenant for a specific rental period, stating the property, the dates covered and the amount due. Landlords use it to make rent a documented transaction rather than an informal transfer. Fill in the fields and download a clean PDF in any currency, with no watermark.',
            'when'=>'Send a rent invoice at the start of each rental period so the tenant has a dated request showing the property, the period covered and the amount due.',
            'fields'=>['Landlord details','Tenant details','Property address','Invoice number and date','Rental period covered','Rent amount and any additional charges','Due date and payment instructions'],
            'faqs'=>[['What is a rent invoice?','A rent invoice is a request for payment of rent for a defined period. It names the property, states the dates covered and the amount due, and gives the tenant a dated document to pay against.'],['Is a rent invoice the same as a rent receipt?','No. The invoice requests rent before or when it falls due. The receipt confirms rent was paid. Many landlords issue both, one at each end of the transaction.'],['Do landlords have to issue rent invoices?','Not usually a legal requirement for residential lets, but it is strongly advisable. A dated invoice makes late payment easy to evidence and removes arguments about what period was covered.'],['Should a rent invoice show the rental period?','Yes, and it is the single most important field. Rent disputes are almost always about which period a payment covered, so state the dates explicitly.'],['Can I include charges other than rent?','Yes. Add lines for service charges, utilities or late fees. Keeping them as separate lines rather than one combined figure makes the invoice much easier for a tenant to accept.']]],
    'rent-receipt'=>['slug'=>'rent-receipt-template','label'=>'Rent receipt','title'=>'Rent receipt template: confirm rent has been paid','kw'=>'rent receipt template','h1'=>'Rent receipt template',
            'answer'=>'A rent receipt confirms that a tenant has paid rent for a specific period. Tenants often need them as proof of payment for deposits, benefit claims, tax relief or reference checks. Fill in the fields and download a clean PDF in any currency, with no watermark.',
            'when'=>'Give a rent receipt each time rent is paid, so the tenant holds dated proof of payment and you hold a matching record.',
            'fields'=>['Landlord details','Tenant details','Property address','Receipt number','Date payment was received','Rental period the payment covers','Amount received and payment method'],
            'faqs'=>[['What is a rent receipt?','A rent receipt is a document confirming rent has been paid. It records who paid, how much, when, and which rental period the payment covers.'],['Why do tenants ask for rent receipts?','Tenants need them as proof of payment for housing benefit claims, tax relief in some countries, landlord references, and deposit disputes. A receipt is often the only evidence that cash rent was paid.'],['What must a rent receipt include?','At minimum: the property address, the amount, the date received, the period covered and the landlord\'s name. The period covered is the field most often left off and most often needed.'],['Is a rent receipt required by law?','It varies by country and by tenancy type. Several jurisdictions require one on request, particularly for cash payment. Issuing one every time avoids the question entirely.'],['Can one receipt cover several months?','Yes, provided the period is stated clearly, for example 1 June to 31 August. Ambiguity about the period is what causes disputes later.']]],
    'job-sheet'=>['slug'=>'job-sheet-template','label'=>'Job sheet','title'=>'Job sheet template: record work done on site','kw'=>'job sheet template','h1'=>'Job sheet template',
            'answer'=>'A job sheet records what work was carried out on a job, how long it took and what parts were used, then captures the customer\'s signature on site. It is the bridge between finishing the work and raising the invoice. Fill in the fields and download a clean PDF, with no watermark.',
            'when'=>'Use a job sheet on site to record the work done, the hours spent and the parts used, and to get the customer to sign it off before you leave.',
            'fields'=>['Your business details','Customer and site details','Job sheet number and date','Date of each visit','Description of work carried out','Hours worked','Parts or materials used','Customer signature'],
            'faqs'=>[['What is a job sheet?','A job sheet is an on-site record of work carried out. It lists what was done, the time spent and the materials used, and is normally signed by the customer to confirm the work was completed.'],['How is a job sheet different from a work order?','A work order authorises work before it starts. A job sheet records what actually happened once it is done. One looks forward, the other looks back.'],['Why get the customer to sign the job sheet?','A signature at the point of completion is the strongest defence against a later dispute over whether work was done or how long it took. It is far harder to challenge weeks afterwards.'],['Does a job sheet show prices?','Usually not. It records work and time, which then feed into a separate invoice. This template leaves prices off so it can be handed to a customer on site without exposing your rates.'],['Can I use a job sheet as an invoice?','It is better to keep them separate. Use the job sheet to agree what was done, then raise an invoice from it. Mixing the two tends to slow down sign-off.']]],
    'retainer-invoice'=>['slug'=>'retainer-invoice-template','label'=>'Retainer invoice','title'=>'Retainer invoice template: bill an ongoing monthly fee','kw'=>'retainer invoice template','h1'=>'Retainer invoice template',
            'answer'=>'A retainer invoice bills a recurring fee for ongoing availability or an agreed scope of work over a period, rather than for a one-off deliverable. Agencies, consultants and freelancers use them to make recurring income predictable. Fill in the fields and download a clean PDF in any currency, with no watermark.',
            'when'=>'Send a retainer invoice at the start of each retained period, stating the period covered and what the retainer includes.',
            'fields'=>['Your business details','Client details','Invoice number and date','Period the retainer covers','What the retainer includes','Retainer amount','Due date and payment instructions'],
            'faqs'=>[['What is a retainer invoice?','A retainer invoice bills a fixed recurring fee for an agreed period, covering ongoing availability or a defined scope rather than a single deliverable.'],['How is a retainer invoice different from a normal invoice?','A normal invoice bills for work already delivered. A retainer invoice bills for a period, and is usually issued in advance of that period rather than after it.'],['Should a retainer invoice list the work included?','Yes. Stating what the retainer covers, and any limits such as hours per month, prevents scope creep and makes the invoice much easier to approve.'],['When should a retainer invoice be sent?','Typically at the start of the period it covers, since a retainer buys availability going forward. Agree the date in the contract and keep it consistent.'],['What happens if the client uses less than the retainer covers?','That depends on your agreement. Some retainers roll unused hours forward, most do not. Whichever you choose, state it on the invoice so there is no ambiguity.']]],
    'refund-note'=>['slug'=>'refund-note-template','label'=>'Refund note','title'=>'Refund note template: confirm money returned to a customer','kw'=>'refund note template','h1'=>'Refund note template',
            'answer'=>'A refund note confirms that money has been returned to a customer, stating the original invoice, the reason and the amount refunded. It closes the loop on a return or a cancelled order. Fill in the fields and download a clean PDF in any currency, with no watermark.',
            'when'=>'Issue a refund note when you return money to a customer, so both sides have a record of what was refunded and why.',
            'fields'=>['Your business details','Customer details','Refund note number and date','Original invoice number','Reason for the refund','Items or amounts refunded','Refund method and total'],
            'faqs'=>[['What is a refund note?','A refund note is a document confirming money has been returned to a customer. It references the original invoice, gives the reason, and states the amount refunded.'],['What is the difference between a refund note and a credit note?','A credit note reduces what a customer owes, often against a future invoice. A refund note confirms money has actually gone back to them. A credit note is a promise, a refund note is a payment.'],['Should a refund note reference the original invoice?','Yes, always. Without the original invoice number neither side can reconcile the refund against the sale, and your accounts will not balance cleanly.'],['Do I need to give a reason for the refund?','It is not always required but it is good practice. A stated reason such as returned goods, cancelled order or overpayment prevents the same query being raised again months later.'],['Is a refund note a tax document?','Treatment varies by country. In many places a refund reverses the tax charged on the original sale, so the refund note should show any tax separately. Check the rules where you operate.']]],
    'progress-billing'=>['slug'=>'progress-billing-template','label'=>'Progress billing','title'=>'Progress billing template: invoice a project in stages','kw'=>'progress billing template','h1'=>'Progress billing template','builds'=>'progress-invoice',
            'answer'=>'Progress billing is invoicing a long project in stages as work is completed, rather than in one payment at the end. Each bill covers a defined percentage or milestone, so cash comes in while the job runs. Fill in the fields and download a clean PDF in any currency, with no watermark.',
            'when'=>'Use progress billing on projects that run over weeks or months, so you are paid as milestones complete rather than waiting until handover.',
            'fields'=>['Your business details','Client and project details','Bill number and date','Stage or percentage being billed','Value of work completed to date','Amount previously billed','Amount now due'],
            'faqs'=>[['What is progress billing?','Progress billing is invoicing a project in instalments as work is completed. Each bill covers an agreed stage or percentage rather than the whole contract.'],['Is progress billing the same as a progress invoice?','Yes. Progress billing describes the practice, a progress invoice is the document it produces. This builder creates the invoice.'],['How do you calculate a progress billing amount?','Take the value of work completed to date, subtract everything already billed, and bill the difference. Showing all three figures on the document is what makes it easy to approve.'],['When should progress billing be used?','On any project long enough that waiting for a single final payment would strain your cash flow. Construction, fit-outs, software builds and long consulting engagements are typical.'],['Should each progress bill show the total contract value?','Yes. Showing contract value, billed to date and the amount now due lets the client see exactly where the project stands financially without opening another document.']]],
    'quotation'=>['slug'=>'quotation-template','label'=>'Quotation','title'=>'Quotation template: give a customer a fixed price','kw'=>'quotation template','h1'=>'Quotation template','builds'=>'quote',
            'answer'=>'A quotation is a fixed price offer for defined work, valid for a stated period. Unlike an estimate, the price does not move unless the scope changes, which is what makes it something a customer can accept. Fill in the fields and download a clean PDF in any currency, with no watermark.',
            'when'=>'Send a quotation when you can commit to a fixed price for clearly defined work, and you want the customer to be able to accept it as it stands.',
            'fields'=>['Your business details','Customer details','Quotation number and date','Valid until date','Itemised description of work with prices','Any tax','Total price and acceptance terms'],
            'faqs'=>[['What is a quotation?','A quotation is a fixed price offer for specified work. Once accepted within its validity period, the price is binding on both sides unless the scope changes.'],['What is the difference between a quotation and an estimate?','A quotation is a firm price you commit to. An estimate is your best approximation and can move as the work becomes clearer. Use a quotation when scope is well defined.'],['Is a quotation and a quote the same thing?','Yes, the words are interchangeable. Quotation is the more formal term and more common in tenders and procurement; quote is the everyday shorthand.'],['How long should a quotation be valid?','Commonly 14 to 30 days. State the date explicitly, because material and labour costs move and an open-ended quotation can be accepted long after it stopped being profitable.'],['Is a quotation legally binding?','Once the customer accepts within the validity period, a quotation generally forms a contract at that price. That is precisely why the validity date and the scope description matter.']]],
    'credit-memo'=>['slug'=>'credit-memo-template','label'=>'Credit memo','title'=>'Credit memo template: reduce what a customer owes','kw'=>'credit memo template','h1'=>'Credit memo template','builds'=>'credit-note',
            'answer'=>'A credit memo reduces the amount a customer owes on an invoice already issued, for a return, an overcharge or a goodwill adjustment. It corrects the account without deleting the original invoice. Fill in the fields and download a clean PDF in any currency, with no watermark.',
            'when'=>'Issue a credit memo when an invoice has already been sent and the amount owed needs to come down, rather than editing or deleting the original.',
            'fields'=>['Your business details','Customer details','Credit memo number and date','Original invoice number','Reason for the credit','Items or amounts being credited','Total credited'],
            'faqs'=>[['What is a credit memo?','A credit memo is a document that reduces the balance a customer owes against an invoice already issued. It records the reason and the amount, leaving the original invoice intact.'],['Is a credit memo the same as a credit note?','Yes. Credit memo is the term used most in the United States, credit note in the UK and Europe. They are the same document and this builder produces both.'],['Why not just edit the original invoice?','Because the original has already been sent and probably recorded in both sets of books. Editing it breaks the audit trail. A credit memo corrects the balance while leaving the history visible.'],['Does a credit memo mean money is refunded?','Not necessarily. A credit memo reduces what is owed and is often set against a future invoice. If money actually goes back to the customer, a refund note records that separately.'],['Should a credit memo reference the original invoice?','Always. Without it neither side can match the credit to the sale, and reconciliation becomes guesswork.']]],
    'debit-memo'=>['slug'=>'debit-memo-template','label'=>'Debit memo','title'=>'Debit memo template: charge a customer more than invoiced','kw'=>'debit memo template','h1'=>'Debit memo template','builds'=>'debit-note',
            'answer'=>'A debit memo increases the amount owed on an invoice already issued, for an undercharge, extra work or a price correction. It adjusts the balance upward without reissuing the original invoice. Fill in the fields and download a clean PDF in any currency, with no watermark.',
            'when'=>'Issue a debit memo when an invoice has already gone out and the amount owed needs to rise, for example after an undercharge or additional work.',
            'fields'=>['Your business details','Customer details','Debit memo number and date','Original invoice number','Reason for the additional charge','Items or amounts being added','Total now due'],
            'faqs'=>[['What is a debit memo?','A debit memo increases the amount a customer owes against an invoice already issued. It documents the reason and the additional amount without altering the original invoice.'],['Is a debit memo the same as a debit note?','Yes. Debit memo is the common term in the United States, debit note in the UK and Europe. The document and its purpose are identical.'],['When would you issue a debit memo?','Typically after undercharging, when extra work was agreed after invoicing, when a price was applied incorrectly, or to pass on a cost increase permitted by the contract.'],['How is a debit memo different from a credit memo?','They move in opposite directions. A credit memo reduces what the customer owes, a debit memo increases it. Both reference the original invoice.'],['Should a debit memo explain the reason?','Yes. An unexplained increase is the fastest route to a dispute. A clear reason and a reference to the original invoice make it far more likely to be paid without argument.']]],
    'waybill'=>['slug'=>'waybill-template','label'=>'Waybill','title'=>'Waybill template: identify a consignment in transit','kw'=>'waybill template','h1'=>'Waybill template',
            'answer'=>'A waybill template is a shipping document that travels with goods, identifying the sender, receiver, and the goods in transit. It is used by carriers to move and track a consignment. Fill in the fields and download a clean PDF.',
            'when'=>'Use a waybill to accompany a consignment, giving the carrier and receiver the details of the sender, destination and goods.',
            'fields'=>['Sender details','Receiver details','Waybill number','Origin and destination','Description of goods and quantity','Weight and number of packages','Carrier and date'],
            'faqs'=>[['What is a waybill?','A waybill is a shipping document that travels with a consignment. It identifies the sender and receiver, describes the goods, and is used by the carrier to move and track the shipment.'],['How is a waybill different from an invoice?','A waybill covers the movement of goods and carries no prices. An invoice requests payment. A shipment often has both, sent for different purposes.'],['Who uses a waybill?','Senders, carriers and receivers use it to identify a consignment in transit and to confirm what was sent and delivered.'],['Does the waybill generator cost anything?','No charge applies in the current release. You can build and download a waybill with no watermark and no card details requested, and without an account. There is no account to create: everything is built and stored in your own browser.'],['What should a waybill include?','Include the sender and receiver details, a waybill number, the origin and destination, a description of the goods and quantity, the weight and number of packages, and the carrier and date.']]],
    ];
}
/**
 * Correct indefinite article for a document label. Lines 890-891 hardcoded "a",
 * which produced "a account balance letter", "a estimate", "a invoice" and
 * "a expense report" in <h2> headings on the live template pages.
 * Rule is by SOUND, not spelling, so the exceptions are listed explicitly.
 */
function indef_article(string $word): string {
    $w = strtolower(ltrim($word));
    // vowel letter, consonant sound
    if (preg_match('/^(uni|use|usu|eu|one|once)/', $w)) return 'a';
    // consonant letter, vowel sound
    if (preg_match('/^(hour|honest|honou?r|heir)/', $w)) return 'an';
    return preg_match('/^[aeiou]/', $w) ? 'an' : 'a';
}

function render_template_page(string $key): void {
    $reg=doc_registry(); $d=$reg[$key]??null; if(!$d){http_response_code(404);echo 'Not found';return;}
    $desc=substr($d['answer'],0,152); $desc=rtrim(substr($desc,0,strrpos($desc,' ')?:152));
    // Build sibling links (3 others)
    $siblings=[];foreach($reg as $k=>$v){if($k!==$key)$siblings[$k]=$v;}$siblings=array_slice($siblings,0,3,true);
    page_header($d['title'],false,$desc);
    // FAQ + HowTo + WebApplication schema, parsed from the same arrays we render
    $howto=['@type'=>'HowTo','name'=>'How to create a '.strtolower($d['label']),'step'=>[['@type'=>'HowToStep','name'=>'Add your details','text'=>'Enter your business and customer details.'],['@type'=>'HowToStep','name'=>'Add line items','text'=>'List the goods or services with quantities, prices and tax.'],['@type'=>'HowToStep','name'=>'Download','text'=>'Download a clean PDF free, with no watermark.']]];
    $app=['@type'=>'WebApplication','name'=>$d['title'],'applicationCategory'=>'BusinessApplication','operatingSystem'=>'Any','offers'=>['@type'=>'Offer','price'=>'0','priceCurrency'=>'USD']];
    echo '<script type="application/ld+json">'.json_encode(['@context'=>'https://schema.org','@graph'=>[$howto,$app]],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).'</script>';
    echo '<article class="landing-sections doc-page">';
    echo '<div class="eyebrow">'.e(strtoupper($d['label'])).' TEMPLATE</div>';
    echo '<h1>'.e($d['h1']).'</h1>';
    echo '<p class="answer-block">'.e($d['answer']).'</p>';
    $target = $d['builds'] ?? $key;
    echo '<div class="actions"><a class="btn" href="/?doc='.e($target).'#invgen">Open the '.e(strtolower($d['label'])).' builder</a></div>';
    $lbl = strtolower($d['label']); $art = indef_article($lbl);
    echo '<h2>When do you use '.$art.' '.e($lbl).'?</h2><p>'.e($d['when']).'</p>';
    echo '<h2>What should '.$art.' '.e($lbl).' include?</h2><ol class="doc-fields">';
    foreach($d['fields'] as $f) echo '<li>'.e($f).'</li>';
    echo '</ol>';
    faq_block($d['faqs']);
    $rel=[];
    foreach($siblings as $sk=>$sv){ $rel[]=['/'.$sv['slug'].'.php',$sv['label'],rtrim(substr($sv['answer'],0,88)).'...']; }
    $rel[]=['/templates.php','All templates','Browse every document generator in one place.'];
    related_strip($rel,'Which related documents might you need?');
    echo '</article>';
    page_footer();
}
/* Renders FAQs AND emits FAQPage schema from the SAME array, so the schema can never
   drift from the visible text. Questions are written in real search phrasing. */
function faq_block(array $faqs, string $heading='Frequently asked questions'): void {
    if(!$faqs) return;
    echo '<h2>'.e($heading).'</h2><div class="faq">';
    foreach($faqs as $f){ echo '<details><summary>'.e($f[0]).'</summary><p>'.e($f[1]).'</p></details>'; }
    echo '</div>';
    $schema=['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>array_map(
        fn($f)=>['@type'=>'Question','name'=>$f[0],'acceptedAnswer'=>['@type'=>'Answer','text'=>$f[1]]],$faqs)];
    echo '<script type="application/ld+json">'.json_encode($schema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).'</script>';
}
/* Related-links strip: real internal links to siblings, passing link equity. */
function related_strip(array $links, string $heading='Related documents'): void {
    if(!$links) return;
    echo '<h2>'.e($heading).'</h2><div class="grid three">';
    foreach($links as $l){ echo '<a class="card sibling" href="'.e($l[0]).'"><strong>'.e($l[1]).'</strong><span>'.e($l[2]).'</span></a>'; }
    echo '</div>';
}
/* Emits an Article node published as the brand: author is the Organization, never a person, and no dates. */
function article_schema(string $headline, string $desc): void {
    $schema=['@context'=>'https://schema.org','@type'=>'Article','headline'=>$headline,'description'=>$desc,
        'author'=>['@type'=>'Organization','name'=>APP_NAME,'url'=>APP_URL.'/'],
        'publisher'=>['@type'=>'Organization','name'=>APP_NAME,'url'=>APP_URL.'/','logo'=>['@type'=>'ImageObject','url'=>APP_URL.'/favicon-512.png']],
        'mainEntityOfPage'=>APP_URL.(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/')];
    echo '<script type="application/ld+json">'.json_encode($schema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).'</script>';
}
function page_header(string $title, bool $app=true, ?string $description=null): void {
    $brand=APP_NAME;
    $description=$description?:'Free invoice generator: build and download invoices, quotes and 24 other business documents as a PDF.';$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';$canonical=APP_URL.($path==='/'?'/':$path);
    $schema=['@context'=>'https://schema.org','@graph'=>[['@type'=>'Organization','@id'=>APP_URL.'/#organization','name'=>APP_NAME,'url'=>APP_URL.'/','email'=>CONTACT_EMAIL,'logo'=>APP_URL.'/favicon-512.png'],['@type'=>'WebSite','@id'=>APP_URL.'/#website','name'=>APP_NAME,'url'=>APP_URL.'/','publisher'=>['@id'=>APP_URL.'/#organization']],['@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>APP_URL.'/'],['@type'=>'ListItem','position'=>2,'name'=>$title,'item'=>$canonical]]]]];
    // Search-engine verification scaffold: only non-empty entries emit a tag, so there are never dangling empties.
    $verifyMeta='';
    foreach(VERIFY as $name=>$content){ if($content!=='') $verifyMeta.='<meta name="'.e($name).'" content="'.e($content).'">'; }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' | '.e($brand).'</title><meta name="description" content="'.e($description).'"><link rel="canonical" href="'.e($canonical).'"><meta name="robots" content="'.($app?'noindex, nofollow':'index, follow, max-image-preview:large, max-snippet:-1').'"><meta name="theme-color" content="#102449">'.$verifyMeta.'<meta property="og:type" content="website"><meta property="og:title" content="'.e($title).' | '.e($brand).'"><meta property="og:description" content="'.e($description).'"><meta property="og:url" content="'.e($canonical).'"><meta property="og:site_name" content="'.e($brand).'"><meta property="og:locale" content="en_GB"><meta property="og:image" content="'.e(APP_URL).'/og-image.png"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="'.e($title).' | '.e($brand).'"><meta name="twitter:description" content="'.e($description).'"><meta name="twitter:image" content="'.e(APP_URL).'/og-image.png"><link rel="icon" href="/favicon.svg" type="image/svg+xml"><link rel="icon" href="/favicon-48.png" sizes="48x48"><link rel="icon" href="/favicon-96.png" sizes="96x96"><link rel="icon" href="/favicon.ico" sizes="any"><link rel="apple-touch-icon" href="/apple-touch-icon.png"><link rel="manifest" href="/site.webmanifest"><link rel="stylesheet" href="/assets/style.css?v=76"><script>try{document.documentElement.dataset.theme=localStorage.getItem("ign_theme")||"light"}catch(e){}</script><script type="application/ld+json">'.json_encode($schema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).'</script></head><body><a class="skip-link" href="#main-content">Skip to main content</a>';
    echo '<header class="top"><a class="brand" href="/"><span class="brand-mark" aria-hidden="true">IG</span>'.e($brand).'</a><button class="nav-toggle" aria-controls="site-nav" aria-expanded="false">Menu</button><nav id="site-nav">';
    { echo '<a href="/features.php">Features</a><a href="/pricing.php">Pricing</a><a href="/templates.php">Templates</a>'
        .'<span id="accessNav" class="access-nav" data-state="loading">'
        .'<a class="btn small" id="accessSignUp" href="/#invgen" data-open-gate="1">Sign up</a>'
        .'<span id="accessAccount" class="access-account" hidden>'
          .'<button type="button" id="accessAvatar" class="avatar-mini" aria-haspopup="true" aria-expanded="false" aria-label="Your access"></button>'
          .'<span id="accessMenu" class="access-menu" hidden>'
            .'<span class="access-email" id="accessEmail"></span>'
            .'<span class="access-note">Every tool unlocked on this browser.</span>'
            .'<button type="button" id="accessSignOut" class="link-btn">Sign out</button>'
          .'</span>'
        .'</span></span>'; }
    echo '<button class="theme-toggle" type="button" aria-pressed="false">Theme</button></nav></header><main class="wrap" id="main-content"><nav class="breadcrumbs" aria-label="Breadcrumb"><a href="/">Home</a> <span aria-hidden="true">/</span> <span>'.e($title).'</span></nav>';
    foreach(flashes() as $f) echo '<div class="flash '.e($f['type']).'">'.e($f['msg']).'</div>';
}
function page_footer(): void { echo '</main><footer><div class="footer-grid"><div><strong>'.e(APP_NAME).'</strong><p>Invoicing and business documents for any country. No payment processing.</p></div><div><strong>Generators</strong><a href="/templates.php">All templates</a><a href="/invoice-template.php">Invoice</a><a href="/tax-invoice-template.php">Tax invoice</a><a href="/commercial-invoice-template.php">Commercial invoice</a><a href="/proforma-invoice-template.php">Proforma invoice</a><a href="/quote-template.php">Quote</a><a href="/estimate-template.php">Estimate</a><a href="/sales-order-template.php">Sales order</a><a href="/deposit-invoice-template.php">Deposit invoice</a><a href="/progress-invoice-template.php">Progress invoice</a><a href="/final-invoice-template.php">Final invoice</a><a href="/receipt-template.php">Receipt</a><a href="/credit-note-template.php">Credit note</a><a href="/debit-note-template.php">Debit note</a></div><div><strong>More documents</strong><a href="/purchase-order-template.php">Purchase order</a><a href="/work-order-template.php">Work order</a><a href="/delivery-note-template.php">Delivery note</a><a href="/packing-slip-template.php">Packing slip</a><a href="/waybill-template.php">Waybill</a><a href="/goods-received-note-template.php">Goods received note</a><a href="/return-note-template.php">Return note</a><a href="/payment-reminder-template.php">Payment reminder</a><a href="/statement-template.php">Client statement</a><a href="/account-balance-letter-template.php">Account balance letter</a><a href="/completion-certificate-template.php">Completion certificate</a><a href="/timesheet-template.php">Timesheet</a><a href="/expense-report-template.php">Expense report</a><a href="/job-sheet-template.php">Job sheet</a></div><div><strong>Logistics &amp; payments</strong><a href="/delivery-challan-template.php">Delivery challan</a><a href="/consignment-note-template.php">Consignment note</a><a href="/remittance-advice-template.php">Remittance advice</a><a href="/payment-voucher-template.php">Payment voucher</a><a href="/cash-receipt-template.php">Cash receipt</a><a href="/refund-note-template.php">Refund note</a><a href="/rent-invoice-template.php">Rent invoice</a><a href="/rent-receipt-template.php">Rent receipt</a><a href="/retainer-invoice-template.php">Retainer invoice</a><a href="/quotation-template.php">Quotation</a><a href="/progress-billing-template.php">Progress billing</a><a href="/credit-memo-template.php">Credit memo</a><a href="/debit-memo-template.php">Debit memo</a></div><div><strong>Guides</strong><a href="/invoice_software_small_business.php">Small business invoicing</a><a href="/quote_and_invoice_software.php">Quotes and invoices</a><a href="/methodology.php">How it works</a><a href="/features.php">Features</a><a href="/pricing.php">Pricing</a><a href="/#invgen">Create a document</a></div><div><strong>Company</strong><a href="/contact.php">Contact</a><a href="/privacy.php">Privacy</a><a href="/terms.php">Terms</a></div></div><p class="footer-note">'.e(APP_NAME).' · '.e(APP_URL).'</p></footer><div class="cookie-banner" role="dialog" aria-label="Cookie choices" hidden><p>We use an essential cookie to remember that your email is verified on this browser. Optional preference storage remembers your theme and cookie choice.</p><div class="actions"><button data-cookie="essential">Essential only</button><button data-cookie="all" class="btn secondary">Allow preferences</button><a href="/privacy.php#cookies">Learn more</a></div></div><div class="sr-live" aria-live="polite" aria-atomic="true"></div><script src="/assets/vendor/jspdf.umd.min.js?v=76" defer></script><script src="/assets/vendor/html2canvas.min.js?v=76" defer></script><script src="/assets/pdf.js?v=76" defer></script><script src="/assets/export.js?v=76" defer></script><script src="/assets/signup.js?v=76" defer></script><script src="/assets/app.js?v=76" defer></script></body></html>'; }
function owned(string $table,int $id): array {
    $allowed=['enquiries','customers','quotes','jobs','invoices','expenses','followups','recurring_jobs']; if(!in_array($table,$allowed,true)) exit('Bad request');
    $st=db()->prepare("SELECT * FROM $table WHERE id=? AND user_id=?"); $st->execute([$id,uid()]); $r=$st->fetch(); if(!$r){http_response_code(404);exit('Not found');} return $r;
}
