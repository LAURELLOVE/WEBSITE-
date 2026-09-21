Cameroon Oncology Center (COC) Website

Plain HTML/CSS pages plus a small PHP backend that stores form submissions in a
MySQL database, emails you an alert, and gives you a password-protected admin page.

FOLDER LAYOUT
  website\                 the finished site (copy this to the host)
    api\                   form backend (submit.php) + settings
    admin\                 password-protected page to read the submissions
    data\                  private folder (only used if you choose SQLite)
    assets\  docs\         photos and PDF documents
  1-configure-site.bat     step 1 below: creates your private settings file
  2-build-upload-file.bat  step 2 below: makes the upload file (coc-website-upload.zip)
  configure-site.ps1, build-for-hosting.ps1   what the two .bat files run
  README.txt, .git ...     project files, NOT uploaded
  team\, design-reference\ your local source photos and mock-ups (git-ignored, not uploaded)

WHAT VISITORS CAN SUBMIT (all saved to the database and emailed to you)
  - Appointment request ...... patients.html#appointment-form  (every "Book an Appointment" button)
  - Contact message .......... contact.html
  - Affiliate network inquiry  network.html#affiliate-form    ("Express Interest" button)
  The appointment form only REQUESTS an appointment - staff must call the person back to
  confirm. It tells visitors to phone the Doctor on Call in an emergency.

=====================================================================
CONNECTING TO NETWORK SOLUTIONS - STEP BY STEP
=====================================================================
Network Solutions' Unix hosting supports PHP, MySQL and .htaccess. Everything below
is done by you, because it needs your account passwords.

IMPORTANT - ONE ACCOUNT, SEVERAL WEBSITES
  Your hosting account may hold more than one domain. Each domain has its OWN folder.
  The files you see at the top level (for example home.html or index.php) can belong to a
  different website. Find the folder that belongs to the COC domain (see step 8) and put
  the site ONLY there. Never overwrite another site's files.

YOUR ACCOUNT (checked 21 Sept 2026 in the Network Solutions control panel)
  - One "Basic Hosting" package holds two domains: camoncenter.org and
    camcancerfoundation.org. Server: PHP 7.4.10, MySQL 5.7.44, working sendmail.
  - camoncenter.org currently points to the folder /wb_camoncenter.org/ (the old
    WebsiteBuilder site, still live). That old site and its folder are NOT touched.
  - The new site goes in the NEW folder  /camoncenter-site/  (already created, empty).
    Do not use the older folders named camoncenter, site_camoncenter, newsite, final...
  - Going live = Websites & Pointers > Pointers & Subdomains > camoncenter.org >
    Subdirectory: change /wb_camoncenter.org/ to /camoncenter-site/ > Save. To go
    back, set it to /wb_camoncenter.org/ again. Email for the domain is not affected.
  - Existing databases include "camoncenter" and "camoncenter01": do not reuse them;
    create a new one (for example camon_site with user camon_user).
  - The database host is NOT "localhost": use the Hostname shown next to the new
    database in MySQL Management (for this account it is of the form
    <account>.ipagemysql.com).
  - Unzipping on the server: File Manager > upload coc-website-upload.zip into
    camoncenter-site, then HOSTING > Archive Gateway > Output Directory
    camoncenter-site (never the top-level ROOT). Delete the zip from the server afterwards,
    because it contains your database password.

A. IN YOUR NETWORK SOLUTIONS ACCOUNT (in a browser)
  1. Log in at networksolutions.com > "Websites & Hosting" > click "Manage" on the
     hosting package for the COC domain.
  2. PHP version: open "PHP Manager" (Configurations section). This site needs PHP 7.3 or
     newer and works on 7.4 and on 8.x. If the domain is on 7.4 it will work as it is;
     upgrading later (only for this domain) is recommended - then re-test every form.
  3. Database: open "Database Manager" (also called "MySQL Management") > MySQL >
     "Add Database". Choose a NEW database name, user and password for COC. Do not reuse
     the database of another website. Write down the database name, user, password and
     the host (normally "localhost"; use whatever the panel shows if it differs).
  4. FTP details: open "FTP Management" / "FTP Account Manager". Note the host,
     the username and the password (create/reset the password there if needed).
     These are different from your Network Solutions login.

B. ON YOUR COMPUTER (double-click, in this project folder)
  5. 1-configure-site.bat
       Asks for the database name/user/password (typed here, hidden), the email
       address that should receive alerts, and an admin password. It writes
       website\api\config.local.php on your computer only (never sent to GitHub).
  6. 2-build-upload-file.bat
       Creates dist\ and coc-website-upload.zip: the exact files to upload.
       dist\ now contains your database password - keep it private, do not share
       or email it.
     (Different domain? In PowerShell run:
        .\build-for-hosting.ps1 -Domain https://www.yourdomain.org )

C. UPLOAD (FileZilla - the client Network Solutions recommends)
  7. New connection:
       Secure (preferred): Protocol SFTP, Host ftp.<your primary domain>,
                           Port 2222, Logon type Normal, your FTP username/password
       Or plain FTP:       Host ftp.<your domain>, Port 21
  8. On the server side, open the folder your COC domain serves from. For the primary
     domain on current hosting this is public_html (older packages use htdocs); an
     additional domain usually has its own sub-folder. If unsure, look in the control
     panel's domain list for the "document root"/"directory" of the COC domain, or ask
     Network Solutions support.
  9. On your side, open the dist\ folder, select EVERYTHING inside it and drag it
     into that server folder. Do not upload the "dist" folder itself. Turn on
     Server > "Force showing hidden files" first so .htaccess is included
     (.htaccess must be sent as text/ASCII and end up with permission 644).
     If the folder already contains an old index.php or home.html of the same site,
     rename them (for example old-index.php) instead of deleting - otherwise the
     server may open the old page first. If the old page still shows, see the
     DirectoryIndex note at the bottom of website\.htaccess.
     If the site shows "500 Internal Server Error" right after, the .htaccess is
     the usual suspect: re-upload it in ASCII mode, or ask support.
     The "data" folder must be writable by PHP only if you use SQLite; with MySQL it
     just needs to exist.

D. CHECK IT WORKS
  10. Open https://yourdomain (home page, menus, photos, PDF downloads).
  11. Open https://yourdomain/admin/ and sign in with the admin password from step 5.
      Click "System check": everything should say OK. (Email alerts and HTTPS may say
      "Check" until you have an SSL certificate.) It also shows the PHP version and the
      PHP extensions the site uses.
      If /admin/ says "Cannot connect to the database", re-check the database host, name,
      user and password (run 1-configure-site.bat again and upload api\config.local.php).
  12. Send a test through EACH form. Each one must appear in /admin/ and arrive by email.
      If email does not arrive, check spam; the submission is still safely stored in
      /admin/. Try a different mail_from (an address that exists on your domain) in
      config.local.php, or ask Network Solutions support about PHP mail settings.
      Then delete the tests in /admin/.

E. LATER
  - SSL/HTTPS: when your certificate is active, run
      .\build-for-hosting.ps1 -Domain https://www.yourdomain.org -ForceHttps
    and re-upload .htaccess. Do NOT do this before https://yourdomain works.
  - After any edit: run step 6 again and upload only the changed files. Never
    delete or overwrite api\config.local.php on the server (or the data folder if
    you use SQLite).
  - If you forget the admin password: run step 5 again, then upload the new
    api\config.local.php.

=====================================================================

USING THE ADMIN (/admin/)
  Filter by type/status, expand a row for the full message, Mark handled, Delete,
  and Export CSV (opens in Excel with accents intact). Sign in is throttled after
  5 wrong passwords. Only YOU can see submissions; visitors cannot.

REQUIREMENTS ON THE HOST
  PHP 7.3 or newer (7.4 and 8.x both fine) and MySQL (or the SQLite extension if you use
  configure-site.ps1 -UseSqlite). Without PHP the pages still display, but the forms
  will show an error.

STILL MISSING FILES (the pages degrade gracefully until you add them)
  website\assets\coc-building-real.png       exterior photo of the hospital (home page banner
                                              and page headers)
  website\docs\ministry-centre-of-excellence-letter.pdf
  website\docs\md-anderson-iroc-imrt-head-neck-report.pdf
  website\docs\md-anderson-radiation-output-quality-check.pdf
  website\assets\quality-thumbs\<same three names>.png   preview pictures of the PDFs
  Add the real files under exactly those names, then run step 6 and upload them.

PRIVACY
  The forms store names, phone numbers, emails and the details typed in. Each form has a
  consent checkbox, but you should also publish a short privacy notice on the site
  describing how this information is used and kept. The forms tell visitors not to send
  test results or medical history; do not ask for medical details or payment card numbers
  through them. Delete handled submissions you no longer need.

PHONES, TABLETS AND DESKTOPS
  Every page adapts to Android/iPhone screens, tablets and desktops. websiteesponsive.css
  and websiteesponsive.js are linked into each page: below 900px wide the long menu
  becomes a single "Menu" button (tap to open/close), buttons and links are at least
  44-48px tall for fingers, inputs do not trigger zoom on iPhones, and nothing scrolls
  sideways. Tested on 33 pages at 360, 412, 768, 820, 1024, 1280 and 1920px wide.
  New page? Add before </head>:  <link rel="stylesheet" href="responsive.css">
  and before </body>:  <script src="responsive.js" defer></script>

LOCAL PREVIEW / TESTING
  Static look only: open website\index.html.
  With working forms (needs PHP, e.g. XAMPP's):  php -S localhost:8080 -t website
  Local test submissions go into website\data\ - the build script removes them from
  dist\ automatically.

NOTES
  - .htaccess (caching, compression, security headers, 404 page, and blocking of the
    private api/data files) works on Network Solutions' Unix/Apache hosting.
  - 404.html uses root-based paths (/assets/...). Edit its references if you host in a
    sub-folder.
  - sitemap.xml and robots.txt default to https://www.camoncenter.org; the -Domain
    option of the build script changes them.
  - Forms are protected against spam by a hidden trap field and a limit of 8
    submissions per visitor per hour (rate_limit_per_hour in api\config.php).
  - The site is in English. A French version would need translated pages and form labels.
