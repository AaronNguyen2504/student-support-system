<?php
// Settings for the Docker / Codespaces demo only. Mounted as config.local.php by docker-compose.yml.
define('DB_HOST', 'db');
define('DB_USER', 'root');
define('DB_PASS', 'demo');
define('DB_NAME', 'student_support');
define('MAIL_LOG_ONLY', true); // e-mails are written to logs/email.log instead of being sent
