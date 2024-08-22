<?php

const API_KEY = 'your_api_key';
const UPLOAD_ROOT_DIR = 'uploads';
const ALLOWED_FILE_EXT = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp'
];
const MAX_FILE_SIZE = 1024 * 1024 * 5; //5MB
const MAX_FILE_AGE = 7 * 24 * 60 * 60; //7 days in seconds