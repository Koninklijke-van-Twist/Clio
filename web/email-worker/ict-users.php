<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

echo json_encode(getIctUsers(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
