<?php

declare(strict_types=1);
require __DIR__ . '/config.php';

logoutUser();

header('Location: index.php');
exit;