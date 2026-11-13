<?php
session_start();

require_once "db.php";

// Count visits using a session
if (!isset($_SESSION['visits'])) {
    $_SESSION['visits'] = 0;
}

$_SESSION['visits']++;

// Get remembered name from cookie
$rememberedName = $_COOKIE['username'] ?? "";

// Handle submitted messages
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST['name'] ?? "");
    $message = trim($_POST['message'] ?? "");
    $remember = isset($_POST['remember']);

    if ($name !== "" && $message !== "") {

        // Save or remove the name cookie
        if ($remember) {
            setcookie("username", $name, [
                "expires" => time() + (86400 * 30),
                "path" => "/",
                "samesite" => "Lax"
            ]);
        } else {
            setcookie("username", "", [
                "expires" => time() - 3600,
                "path" => "/",
                "samesite" => "Lax"
            ]);
        }

        // Insert message using prepared statement
        $sql = "INSERT INTO messages (author, body)
                VALUES (:author, :body)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':author' => $name,
            ':body' => $message
        ]);

        // Prevent duplicate submissions
        header("Location: index.php");
        exit;
    } else {
        $error = "Please enter your name and message.";
    }
}

// Handle search using GET
$search = trim($_GET['search'] ?? "");

if ($search !== "") {

    $sql = "SELECT * FROM messages
            WHERE author ILIKE :search
            OR body ILIKE :search
            ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':search' => '%' . $search . '%'
    ]);

} else {

    // Display all messages, newest first
    $sql = "SELECT * FROM messages
            ORDER BY created_at DESC";

    $stmt = $pdo->query($sql);
}

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Class Message Board</title>

    <style>
        :root {
            color-scheme: light;
            --rose: #b84d78;
            --rose-dark: #873758;
            --rose-soft: #f9e8ef;
            --ink: #3e2935;
            --muted: #806b76;
            --line: #f0dce5;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 48px 20px;
            background:
                radial-gradient(ellipse at top left, #fff8fb 0, transparent 42%),
                linear-gradient(135deg, #f8e9ef, #f5eef2 55%, #f6e5ec);
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .container {
            max-width: 760px;
            margin: 0 auto;
            padding: 42px;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(111, 53, 78, 0.13);
        }

        h1 {
            text-align: center;
            margin: 0 0 12px;
            color: var(--rose-dark);
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(2rem, 5vw, 2.8rem);
            letter-spacing: -0.04em;
        }

        h2 {
            margin: 32px 0 14px;
            color: var(--rose-dark);
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.45rem;
        }

        label {
            display: block;
            margin: 16px 0 7px;
            color: var(--ink);
            font-size: 0.94rem;
            font-weight: 600;
        }

        input, textarea {
            width: 100%;
            margin: 0 0 12px;
            padding: 13px 15px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fffafd;
            color: var(--ink);
            font: inherit;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        textarea {
            min-height: 130px;
            resize: vertical;
        }

        input:focus, textarea:focus {
            border-color: var(--rose);
            outline: none;
            box-shadow: 0 0 0 4px rgba(184, 77, 120, 0.13);
        }

        input[type="checkbox"] {
            width: auto;
            margin: 0 8px 0 0;
            accent-color: var(--rose);
            vertical-align: middle;
        }

        label.remember {
            display: flex;
            align-items: center;
            margin: 2px 0 18px;
            color: var(--muted);
            font-weight: 400;
        }

        button {
            padding: 12px 20px;
            border: 0;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--rose), var(--rose-dark));
            box-shadow: 0 7px 16px rgba(135, 55, 88, 0.2);
            color: #fff;
            font: inherit;
            font-weight: 600;
            letter-spacing: 0.01em;
            cursor: pointer;
            transition: transform 160ms ease, box-shadow 160ms ease;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(135, 55, 88, 0.25);
        }

        button:focus-visible, a:focus-visible {
            outline: 3px solid rgba(184, 77, 120, 0.35);
            outline-offset: 3px;
        }

        a {
            margin-left: 12px;
            color: var(--rose-dark);
            font-weight: 600;
            text-decoration-color: #dfb4c6;
            text-underline-offset: 3px;
        }

        a:hover {
            color: var(--rose);
        }

        hr {
            height: 1px;
            margin: 30px 0;
            border: none;
            background: var(--line);
        }

        .message {
            margin-top: 14px;
            padding: 20px 22px;
            border: 1px solid var(--line);
            border-left: 4px solid #d783a3;
            border-radius: 14px;
            background: linear-gradient(135deg, #fffafd, #fff);
            box-shadow: 0 5px 16px rgba(111, 53, 78, 0.05);
        }

        .message strong {
            color: var(--rose-dark);
            font-size: 1.04rem;
        }

        .message p {
            line-height: 1.65;
            overflow-wrap: anywhere;
        }

        .message small {
            color: var(--muted);
        }

        .error {
            padding: 12px 14px;
            border: 1px solid #f1c4d2;
            border-radius: 10px;
            background: #fff3f6;
            color: #9c3152;
        }

        .info {
            margin: 0 auto 28px;
            color: var(--muted);
            text-align: center;
        }

        @media (max-width: 600px) {
            body {
                padding: 20px 12px;
            }

            .container {
                padding: 26px 20px;
                border-radius: 18px;
            }

            h2 {
                margin-top: 28px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Class Message Board</h1>

    <p class="info">
        You've visited this page
        <strong><?= $_SESSION['visits'] ?></strong>
        times during this session.
    </p>

    <h2>Post a Message</h2>

    <?php if (isset($error)): ?>
        <p class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="index.php">

        <label>Your Name:</label>

        <input
            type="text"
            name="name"
            placeholder="Enter your name"
            value="<?= htmlspecialchars($rememberedName, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label>Your Message:</label>

        <textarea
            name="message"
            placeholder="Write your message here..."
            required
        ></textarea>

        <label class="remember">
            <input
                type="checkbox"
                name="remember"
                value="1"
                style="width: auto;"
                <?= isset($_COOKIE['username']) ? 'checked' : '' ?>
            >
            Remember my name
        </label>

        <br><br>

        <button type="submit">Post Message</button>

    </form>

    <hr>

    <h2>Search Messages</h2>

    <form method="GET" action="index.php">

        <input
            type="text"
            name="search"
            placeholder="Search messages..."
            value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
        >

        <button type="submit">Search</button>

        <a href="index.php">Show All</a>

    </form>

    <hr>

    <h2>
        <?= $search !== "" ? "Search Results" : "All Messages" ?>
    </h2>

    <?php if (count($messages) > 0): ?>

        <?php foreach ($messages as $row): ?>

            <div class="message">

                <strong>
                    <?= htmlspecialchars($row['author'], ENT_QUOTES, 'UTF-8') ?>
                </strong>

                <p>
                    <?= nl2br(htmlspecialchars($row['body'], ENT_QUOTES, 'UTF-8')) ?>
                </p>

                <small>
                    <?= htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8') ?>
                </small>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <p>No messages found.</p>

    <?php endif; ?>

</div>

</body>
</html>
