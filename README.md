# FileAPI

A tiny file-hosting service with an HTTP upload API. Send an image, get back a
public URL. Written in plain PHP with no runtime dependencies — just copy the
files onto a server.

## Requirements

- **PHP 8.0+** (uses attributes; typed properties). PHP 8.1+ recommended.
- The `fileinfo` extension (`ext-fileinfo`) — enabled by default in most PHP builds.
- A web server (Apache, nginx, or PHP's built-in server) with write access to the upload directory.

No Composer install is needed — `vendor/` only contains a static autoloader and
is optional for the current code.

## Installation

1. Copy the project files onto your server so that `index.php` is web-accessible
   (i.e. the project root is your document root, or a subfolder of it).
2. Create your config from the template:
   ```bash
   cp settings.dist.php settings.php
   ```
3. Edit `settings.php` and set a strong `API_KEY` (see below). `settings.php` is
   git-ignored so your key never gets committed.
4. Make sure the `uploads/` directory exists and is writable by the web server:
   ```bash
   mkdir -p uploads && chmod 755 uploads
   ```
5. Visit `https://your-host/` — `index.php` should print a confirmation message.

## Configuration (`settings.php`)

| Constant           | Meaning                                                                 |
|--------------------|-------------------------------------------------------------------------|
| `API_KEY`          | Secret key required on every upload request.                            |
| `CORS_ALLOW_ORIGIN`| Value sent in the `Access-Control-Allow-Origin` header. Default `*` (any origin); set to a specific origin like `https://example.com` to restrict it. |
| `UPLOAD_ROOT_DIR`  | Directory (relative to the document root) where files are stored.       |
| `ALLOWED_FILE_EXT` | Map of allowed MIME types → file extension. Uploads of other types are rejected. |
| `MAX_FILE_SIZE`    | Maximum file size in bytes (default 5 MB).                              |
| `MAX_FILE_AGE`     | How long files are kept before the cleanup cron deletes them (seconds). |

Default allowed types: `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/avif`.

## Uploading

The endpoint is `api/upload.php`. It accepts **POST** only, and there are two
ways to send a file. The server decides which based on the `Content-Type` header.

Uploaded files are stored under `UPLOAD_ROOT_DIR/YYYYMMDD/` by default, or under
`UPLOAD_ROOT_DIR/custom/` if you request the custom path. Each file gets a unique
generated name; the extension is derived from the detected MIME type.

### 1. Streaming (recommended for large files)

Send the **raw binary file** as the request body. The server streams it straight
to disk in small chunks, so memory usage stays flat regardless of file size.
Metadata travels in the header / query string instead of the body:

- API key: `X-Api-Key` header
- Optional custom path: `?path=custom` query parameter

**curl:**
```bash
curl -X POST "https://your-host/api/upload.php" \
  -H "X-Api-Key: your_api_key" \
  -H "Content-Type: application/octet-stream" \
  --data-binary @photo.jpg
```

**Node.js** (constant memory on the client too):
```javascript
const fs = require('fs');
const https = require('https');

const req = https.request('https://your-host/api/upload.php?path=custom', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/octet-stream',
    'X-Api-Key': 'your_api_key',
  },
}, res => {
  let body = '';
  res.on('data', c => (body += c));
  res.on('end', () => console.log(res.statusCode, body));
});

fs.createReadStream('big-image.jpg').pipe(req);
```

### 2. Base64 in JSON (legacy)

Send a JSON body with the base64-encoded image. Simple, but the whole payload is
buffered in memory on both ends — fine for small images only.

- `Content-Type: application/json` (this is what selects this path)
- `key` — the API key
- `image` — base64-encoded file contents
- `path` — optional, set to `"custom"` for the custom directory

```bash
curl -X POST "https://your-host/api/upload.php" \
  -H "Content-Type: application/json" \
  -d '{
        "key": "your_api_key",
        "image": "'"$(base64 -w0 photo.jpg)"'",
        "path": "custom"
      }'
```

> Note: in this path the size limit is checked against the base64 string length,
> which is ~1.33× the binary size — so the effective file cap is lower than
> `MAX_FILE_SIZE`. The streaming path checks the real byte count.

## Responses

**Success (HTTP 200):**
```json
{
  "status": "success",
  "message": "Image saved successfully",
  "url": "https://your-host/uploads/20260912/650f...c3.jpg"
}
```

**Error (HTTP 4xx / 5xx):**
```json
{
  "status": 400,
  "message": "Invalid mime type"
}
```

Common errors:

| Code | Message                          | Cause                                            |
|------|----------------------------------|--------------------------------------------------|
| 405  | Request method not allowed       | Not a POST request.                              |
| 400  | Invalid API key / API key not provided | Missing or wrong key.                      |
| 400  | Missing image                    | Empty body / no `image` field.                   |
| 400  | Max file size ... exceeded       | File larger than `MAX_FILE_SIZE`.                |
| 400  | Invalid mime type                | Type not in `ALLOWED_FILE_EXT`.                  |
| 400  | Invalid base64 image             | Base64 body could not be decoded.                |
| 500  | Cannot write to file / Unable to create directory | Filesystem permission problem.  |

## Automatic cleanup (cron)

`cron/delete_expired_files.php` removes files older than `MAX_FILE_AGE` (files in
the `custom/` folder are kept). Run it from the project root on a schedule, e.g.
daily:

```bash
0 3 * * * cd /path/to/FileAPI && php cron/delete_expired_files.php >> logs/cron.log 2>&1
```

## Notes

- CORS defaults to open (`Access-Control-Allow-Origin: *`); set `CORS_ALLOW_ORIGIN`
  in `settings.php` to a specific origin if you only serve one.
- The API key is sent in clear text — always serve the endpoint over **HTTPS**.
