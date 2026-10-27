<?php
// ============================================================
// index.php - Contact Manager (Frontend + Backend API)
// ============================================================

// ---------- DB Config ----------
$host = 'localhost';
$dbname = 'contacts_db';
$username = 'root';       // default XAMPP username
$password = '';           // default XAMPP password

// ---------- Connect to DB & Auto-Initialize ----------
try {
    // First connect to MySQL server to ensure database and table exist
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // Create table if not exists (name + phone, without email)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contacts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `phone` VARCHAR(30) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // If an older contacts table has the email column, remove it
    $emailCol = $pdo->query("SHOW COLUMNS FROM `contacts` LIKE 'email'")->fetch();
    if ($emailCol) {
        $pdo->exec("ALTER TABLE `contacts` DROP COLUMN `email`");
    }
} catch (PDOException $e) {
    if (isset($_GET['api'])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit();
    }
    // Beautiful friendly error screen when loaded directly in browser
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Connection Error</title>
        <style>
            body {
                font-family: 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #fce8e6 0%, #f7fafc 100%);
                color: #2d3748;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                padding: 1rem;
            }
            .card {
                background: white;
                padding: 2.5rem;
                border-radius: 1.5rem;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                max-width: 560px;
                width: 100%;
                border-left: 6px solid #e53e3e;
            }
            h2 { color: #c53030; margin-top: 0; display: flex; align-items: center; gap: 0.5rem; }
            p { line-height: 1.6; margin: 0.8rem 0; }
            .error-box {
                background: #fff5f5;
                border: 1px solid #fed7d7;
                color: #9b2c2c;
                padding: 0.8rem 1rem;
                border-radius: 0.5rem;
                font-family: monospace;
                font-size: 0.9rem;
                word-break: break-word;
                margin: 1rem 0;
            }
            ol { padding-left: 1.3rem; line-height: 1.7; }
            .btn {
                background: #1e3f3a;
                color: white;
                border: none;
                padding: 0.75rem 1.5rem;
                border-radius: 2rem;
                font-size: 0.95rem;
                font-weight: 600;
                cursor: pointer;
                margin-top: 1rem;
            }
            .btn:hover { background: #15312d; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>⚠️ Database Connection Failed</h2>
            <p>Could not connect to the MySQL database server.</p>
            <div class="error-box"><?= htmlspecialchars($e->getMessage()) ?></div>
            <p><strong>To resolve this issue:</strong></p>
            <ol>
                <li>Open the <strong>XAMPP Control Panel</strong>.</li>
                <li>Make sure the <strong>MySQL</strong> module is started (status should be green).</li>
                <li>Verify your database configuration in <code>index.php</code> (host: <code><?= htmlspecialchars($host) ?></code>, user: <code><?= htmlspecialchars($username) ?></code>).</li>
            </ol>
            <button class="btn" onclick="location.reload()">🔄 Retry Connection</button>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ============================================================
// API HANDLER — only runs when ?api is present
// ============================================================
if (isset($_GET['api'])) {
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // Helper: read JSON body
    function getJsonInput() {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    // Helper: send JSON
    function sendJson($data, $status = 200) {
        http_response_code($status);
        echo json_encode($data);
        exit();
    }

    // ---------- GET: list all contacts ----------
    if ($method === 'GET') {
        try {
            $stmt = $pdo->query("SELECT id, name, phone, created_at FROM contacts ORDER BY id DESC");
            sendJson($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            sendJson(['error' => 'Fetch failed: ' . $e->getMessage()], 500);
        }
    }

    // ---------- POST: create contact ----------
    elseif ($method === 'POST') {
        $input = getJsonInput();
        $name  = trim($input['name'] ?? '');
        $phone = trim($input['phone'] ?? '');

        if ($name === '') {
            sendJson(['error' => 'Contact name is required'], 400);
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO contacts (name, phone) VALUES (?, ?)");
            $stmt->execute([$name, $phone !== '' ? $phone : null]);
            sendJson(['success' => true, 'id' => $pdo->lastInsertId()], 201);
        } catch (PDOException $e) {
            sendJson(['error' => 'Insert failed: ' . $e->getMessage()], 500);
        }
    }

    // ---------- PUT: update contact ----------
    elseif ($method === 'PUT') {
        $input = getJsonInput();
        $id    = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT);
        $name  = trim($input['name'] ?? '');
        $phone = trim($input['phone'] ?? '');

        if (!$id || $name === '') {
            sendJson(['error' => 'ID and name are required'], 400);
        }

        try {
            $check = $pdo->prepare("SELECT id FROM contacts WHERE id = ?");
            $check->execute([$id]);
            if ($check->rowCount() === 0) {
                sendJson(['error' => 'Contact not found'], 404);
            }

            $stmt = $pdo->prepare("UPDATE contacts SET name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $phone !== '' ? $phone : null, $id]);
            sendJson(['success' => true, 'message' => 'Contact updated']);
        } catch (PDOException $e) {
            sendJson(['error' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    // ---------- DELETE: remove contact ----------
    elseif ($method === 'DELETE') {
        $id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$id) {
            sendJson(['error' => 'Valid ID required'], 400);
        }

        try {
            $check = $pdo->prepare("SELECT id FROM contacts WHERE id = ?");
            $check->execute([$id]);
            if ($check->rowCount() === 0) {
                sendJson(['error' => 'Contact not found'], 404);
            }

            $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
            $stmt->execute([$id]);
            sendJson(['success' => true, 'message' => 'Contact deleted']);
        } catch (PDOException $e) {
            sendJson(['error' => 'Delete failed: ' . $e->getMessage()], 500);
        }
    }

    else {
        sendJson(['error' => 'Method not allowed'], 405);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Manager · PHP + MySQL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Roboto, system-ui, sans-serif; }
        body {
            background: linear-gradient(145deg, #eef2f7 0%, #d9e2ec 100%);
            min-height: 100vh;
            display: flex; justify-content: center; align-items: center;
            padding: 1.5rem;
        }
        .app-container {
            max-width: 1000px; width: 100%;
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            border-radius: 2.5rem;
            box-shadow: 0 25px 50px -10px rgba(0,20,30,0.3), inset 0 1px 2px rgba(255,255,255,0.7);
            padding: 2rem 2rem 2.5rem;
            border: 1px solid rgba(255,255,255,0.5);
        }
        h1 {
            font-size: 2.4rem; font-weight: 600; color: #1e2b3c;
            display: flex; align-items: center; gap: 0.75rem;
            margin-bottom: 1.8rem; letter-spacing: -0.5px;
        }
        h1 i {
            color: #2d6a4f; background: #d0e6df;
            padding: 0.6rem; border-radius: 50%; font-size: 1.6rem;
        }
        .form-card {
            background: #ffffffdd; backdrop-filter: blur(8px);
            border-radius: 2rem; padding: 1.8rem 2rem; margin-bottom: 2.5rem;
            box-shadow: 0 10px 20px -8px rgba(0,0,0,0.1);
            border: 1px solid #ffffffaa;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1.2rem;
            align-items: end;
        }
        .input-group { display: flex; flex-direction: column; gap: 0.4rem; }
        .input-group label {
            font-size: 0.85rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.5px;
            color: #2d3e53; margin-left: 0.3rem;
        }
        .input-group input {
            padding: 0.85rem 1.2rem; border-radius: 1.2rem;
            border: 1.5px solid #cbd5e1; background: #f9fcff;
            font-size: 0.95rem; transition: all 0.2s ease; outline: none;
        }
        .input-group input:focus {
            border-color: #2d6a4f;
            box-shadow: 0 0 0 4px rgba(45,106,79,0.15);
            background: #ffffff;
        }
        .action-buttons { display: flex; gap: 0.8rem; flex-wrap: wrap; }
        .btn {
            border: none; padding: 0.85rem 1.8rem; border-radius: 2.5rem;
            font-weight: 600; font-size: 0.95rem;
            display: inline-flex; align-items: center; justify-content: center; gap: 0.6rem;
            cursor: pointer; transition: all 0.15s ease;
            background: white; color: #1e2b3c;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02);
            border: 1px solid #d0dae8;
            white-space: nowrap;
        }
        .btn-primary {
            background: #1e3f3a; color: white; border: 1px solid #1e3f3a;
            box-shadow: 0 8px 16px -6px #1e3f3a80;
        }
        .btn-primary:hover {
            background: #15312d; transform: translateY(-2px);
            box-shadow: 0 14px 20px -8px #1e3f3a;
        }
        .btn-secondary { background: #eef2f7; border: 1px solid #bcccdb; }
        .btn-secondary:hover { background: #dfe7ef; transform: translateY(-2px); }
        .btn i { font-size: 1rem; }
        .table-wrapper {
            background: #ffffffdd; backdrop-filter: blur(6px);
            border-radius: 2rem; padding: 0.2rem 0;
            box-shadow: 0 10px 30px -12px rgba(0,0,0,0.2);
            border: 1px solid #ffffffcc; overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th {
            text-align: left; padding: 1.2rem 1.5rem 0.9rem;
            font-weight: 600; font-size: 0.85rem; text-transform: uppercase;
            letter-spacing: 0.6px; color: #295b4b; background: #eaf4f0;
            border-bottom: 2px solid #bcd9cf;
        }
        td {
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid #d9e6e1;
            color: #1f2e3c; font-size: 0.95rem;
            background: #ffffffb0; transition: background 0.15s;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f5fbf9; }
        .actions-cell { display: flex; gap: 0.6rem; justify-content: flex-end; }
        .icon-btn {
            background: transparent; border: none; font-size: 0.95rem;
            padding: 0.45rem 0.9rem; border-radius: 1.8rem;
            cursor: pointer; transition: all 0.15s;
            color: #3f5e72; display: inline-flex; align-items: center;
            gap: 0.4rem; border: 1px solid transparent;
        }
        .icon-btn.edit-btn:hover {
            background: #d9efe8; color: #1b6b4f; border-color: #9ccfbc;
        }
        .icon-btn.delete-btn:hover {
            background: #fce4e4; color: #b23b3b; border-color: #e6b3b3;
        }
        .empty-row td {
            text-align: center; padding: 3rem 1rem;
            color: #5f7a8c; font-style: italic; background: #f9fdfb;
        }
        .toast-message {
            position: fixed; bottom: 2rem; right: 2rem;
            background: #1e3f3a; color: white;
            padding: 1rem 1.8rem; border-radius: 3rem;
            font-weight: 500; box-shadow: 0 15px 25px -8px rgba(0,0,0,0.3);
            display: flex; align-items: center; gap: 1rem;
            opacity: 0; transform: translateY(1rem);
            transition: opacity 0.3s, transform 0.3s;
            pointer-events: none; z-index: 999; font-size: 0.95rem;
        }
        .toast-message.show { opacity: 1; transform: translateY(0); }
        .toast-message i { font-size: 1.4rem; }
        @media (max-width: 768px) {
            .app-container { padding: 1.5rem 1rem; }
            h1 { font-size: 1.9rem; }
            .form-card { padding: 1.4rem; }
            .form-grid { grid-template-columns: 1fr; }
            .btn { width: 100%; }
            th, td { padding-left: 1rem; padding-right: 0.8rem; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <h1><i class="fas fa-address-book"></i> Contact Manager</h1>

    <!-- Form -->
    <div class="form-card">
        <form id="contactForm">
            <input type="hidden" id="contactId" value="">
            <div class="form-grid">
                <div class="input-group">
                    <label for="name"><i class="fas fa-user"></i> Full name</label>
                    <input type="text" id="name" required>
                </div>
                <div class="input-group">
                    <label for="phone"><i class="fas fa-phone-alt"></i> Phone number</label>
                    <input type="tel" id="phone">
                </div>
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-plus-circle"></i> Add contact
                    </button>
                    <button type="button" class="btn btn-secondary" id="cancelEditBtn" style="display:none;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th style="width:180px; text-align:right; padding-right: 1.5rem;">Actions</th>
                </tr>
            </thead>
            <tbody id="contactTableBody">
                <tr class="empty-row">
                    <td colspan="3"><i class="fas fa-spinner fa-pulse"></i> Loading contacts…</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div id="toast" class="toast-message">
    <i class="fas fa-check-circle"></i>
    <span id="toastText">Contact saved</span>
</div>

<script>
const API_URL = 'index.php?api=1';

const contactForm    = document.getElementById('contactForm');
const contactIdField = document.getElementById('contactId');
const nameInput      = document.getElementById('name');
const phoneInput     = document.getElementById('phone');
const submitBtn      = document.getElementById('submitBtn');
const cancelEditBtn  = document.getElementById('cancelEditBtn');
const tableBody      = document.getElementById('contactTableBody');
const toast          = document.getElementById('toast');
const toastText      = document.getElementById('toastText');

let isEditing = false;

function showToast(message, isError = false) {
    toastText.textContent = message;
    toast.style.background = isError ? '#b23b3b' : '#1e3f3a';
    toast.querySelector('i').className = isError
        ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2800);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"]/g, m => (
        { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;' }[m]
    ));
}
function escapeAttr(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

async function loadContacts() {
    try {
        const res = await fetch(API_URL);
        if (!res.ok) {
            const errData = await res.json().catch(() => ({}));
            throw new Error(errData.error || 'Failed to load contacts');
        }
        const contacts = await res.json();
        renderContacts(contacts);
    } catch (err) {
        console.error(err);
        tableBody.innerHTML = `<tr class="empty-row"><td colspan="3"><i class="fas fa-exclamation-triangle"></i> Failed to load contacts: ${escapeHtml(err.message)}</td></tr>`;
        showToast(err.message, true);
    }
}

function renderContacts(contacts) {
    if (!contacts || contacts.length === 0) {
        tableBody.innerHTML = `<tr class="empty-row"><td colspan="3"><i class="fas fa-address-book"></i> No contacts yet. Add one above.</td></tr>`;
        return;
    }
    tableBody.innerHTML = contacts.map(c => {
        const id    = c.id;
        const name  = escapeHtml(c.name);
        const phone = escapeHtml(c.phone || '—');
        return `
            <tr>
                <td><i class="fas fa-user-circle" style="color:#2d6a4f;margin-right:8px;"></i><strong>${name}</strong></td>
                <td><i class="fas fa-phone" style="color:#5f7a8c;margin-right:6px;"></i>${phone}</td>
                <td class="actions-cell">
                    <button class="icon-btn edit-btn"
                        data-id="${id}"
                        data-name="${escapeAttr(c.name)}"
                        data-phone="${escapeAttr(c.phone || '')}">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="icon-btn delete-btn" data-id="${id}">
                        <i class="fas fa-trash-alt"></i> Delete
                    </button>
                </td>
            </tr>`;
    }).join('');

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            enterEditMode(btn.dataset.id, btn.dataset.name, btn.dataset.phone);
        });
    });
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (confirm('Are you sure you want to delete this contact?')) {
                deleteContact(btn.dataset.id);
            }
        });
    });
}

function enterEditMode(id, name, phone) {
    isEditing = true;
    contactIdField.value = id;
    nameInput.value  = name;
    phoneInput.value = phone || '';
    submitBtn.innerHTML = '<i class="fas fa-save"></i> Update contact';
    cancelEditBtn.style.display = 'inline-flex';
    nameInput.focus();
}

function resetForm() {
    isEditing = false;
    contactIdField.value = '';
    contactForm.reset();
    submitBtn.innerHTML = '<i class="fas fa-plus-circle"></i> Add contact';
    cancelEditBtn.style.display = 'none';
    nameInput.focus();
}

cancelEditBtn.addEventListener('click', resetForm);

contactForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name  = nameInput.value.trim();
    const phone = phoneInput.value.trim();

    if (!name) {
        showToast('Contact name is required', true);
        nameInput.focus();
        return;
    }

    const data = { name, phone };
    const id   = contactIdField.value;

    try {
        let res;
        if (isEditing && id) {
            data.id = parseInt(id);
            res = await fetch(API_URL, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        } else {
            res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        }
        const result = await res.json();
        if (!res.ok) throw new Error(result.error || 'Request failed');
        showToast(isEditing ? 'Contact updated successfully' : 'Contact added successfully');
        resetForm();
        loadContacts();
    } catch (err) {
        console.error(err);
        showToast(err.message, true);
    }
});

async function deleteContact(id) {
    try {
        const res = await fetch(`${API_URL}&id=${id}`, { method: 'DELETE' });
        const result = await res.json();
        if (!res.ok) throw new Error(result.error || 'Delete failed');
        showToast('Contact deleted');
        if (isEditing && contactIdField.value == id) resetForm();
        loadContacts();
    } catch (err) {
        console.error(err);
        showToast(err.message, true);
    }
}

window.addEventListener('DOMContentLoaded', () => {
    loadContacts();
    nameInput.focus();
});
</script>
</body>
</html>