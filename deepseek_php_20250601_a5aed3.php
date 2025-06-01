<?php
// ======================== CONFIGURATION & DATABASE ========================
$host = "localhost";
$username = "root";
$password = "";
$database = "social_media";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session
session_start();

// Minecraft server configuration
$java_ip = "silencesmps3.sytes.net:19133";
$bedrock_ip = "silencesmps3.sytes.net";
$bedrock_port = "19133";

// Handle login
if (isset($_POST['login'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $conn->real_escape_string($_POST['password']);

    $sql = "SELECT * FROM users WHERE username='$username' OR email='$username'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['profile_pic'] = $user['profile_pic'];
            header("Location: ".$_SERVER['PHP_SELF']);
        } else {
            $login_error = "Username atau password salah";
        }
    } else {
        $login_error = "Username atau password salah";
    }
}

// Handle registration
if (isset($_POST['register'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($conn->real_escape_string($_POST['password']), PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (full_name, username, email, password) VALUES ('$full_name', '$username', '$email', '$password')";

    if ($conn->query($sql) {
        $registration_success = "Registrasi berhasil! Silakan login.";
    } else {
        $registration_error = "Error: " . $sql . "<br>" . $conn->error;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ".$_SERVER['PHP_SELF']);
}

// Handle post creation
if (isset($_POST['create_post']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $content = $conn->real_escape_string($_POST['content']);
    
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $target_file = $target_dir . basename($_FILES["image"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check !== false) {
            $image = uniqid() . '.' . $imageFileType;
            $target_file = $target_dir . $image;
            
            if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image = null;
            }
        }
    }
    
    $sql = "INSERT INTO posts (user_id, content, image) VALUES ('$user_id', '$content', '$image')";
    $conn->query($sql);
}

// Handle profile update
if (isset($_POST['update_profile']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $description = $conn->real_escape_string($_POST['description']);
    $minecraft_username = $conn->real_escape_string($_POST['minecraft_username']);
    
    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $target_dir = "uploads/";
        $target_file = $target_dir . uniqid() . '.' . pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION);
        
        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            $profile_pic = basename($target_file);
            $sql = "UPDATE users SET profile_pic = '$profile_pic' WHERE id = $user_id";
            $conn->query($sql);
            $_SESSION['profile_pic'] = $profile_pic;
        }
    }
    
    // Handle banner upload
    if (isset($_FILES['banner']) && $_FILES['banner']['error'] == 0) {
        $target_dir = "uploads/";
        $target_file = $target_dir . uniqid() . '.' . pathinfo($_FILES["banner"]["name"], PATHINFO_EXTENSION);
        
        if (move_uploaded_file($_FILES["banner"]["tmp_name"], $target_file)) {
            $banner = basename($target_file);
            $sql = "UPDATE users SET banner = '$banner' WHERE id = $user_id";
            $conn->query($sql);
        }
    }
    
    // Update other fields
    $sql = "UPDATE users SET 
            full_name = '$full_name',
            description = '$description',
            minecraft_username = '$minecraft_username'
            WHERE id = $user_id";
    
    $conn->query($sql);
    $_SESSION['full_name'] = $full_name;
}

// Get current page
$current_page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Get user data if logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT * FROM users WHERE id = $user_id";
    $result = $conn->query($sql);
    $current_user = $result->fetch_assoc();
}

// Get posts for home page
if (isset($_SESSION['user_id']) && $current_page == 'home') {
    $sql = "SELECT posts.*, users.username, users.full_name, users.profile_pic 
            FROM posts 
            JOIN users ON posts.user_id = users.id 
            ORDER BY posts.created_at DESC";
    $result = $conn->query($sql);
    $posts = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $posts[] = $row;
        }
    }
}

// Get profile data if viewing profile
if ($current_page == 'profile') {
    $profile_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
    if ($profile_id > 0) {
        $sql = "SELECT * FROM users WHERE id = $profile_id";
        $result = $conn->query($sql);
        $profile = $result->fetch_assoc();
        
        // Get friends count
        $friends_sql = "SELECT COUNT(*) as count FROM friends WHERE (user_id = $profile_id OR friend_id = $profile_id) AND status = 'accepted'";
        $friends_result = $conn->query($friends_sql);
        $friends_count = $friends_result->fetch_assoc()['count'];
        
        // Get user posts
        $posts_sql = "SELECT * FROM posts WHERE user_id = $profile_id ORDER BY created_at DESC";
        $posts_result = $conn->query($posts_sql);
        $profile_posts = [];
        if ($posts_result->num_rows > 0) {
            while ($row = $posts_result->fetch_assoc()) {
                $profile_posts[] = $row;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Media</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Global Styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            color: #333;
            line-height: 1.6;
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        button, input[type="submit"], input[type="button"] {
            cursor: pointer;
        }
        
        /* Header Styles */
        .header {
            background-color: #1877f2;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
        }
        
        .nav {
            display: flex;
            gap: 20px;
        }
        
        .nav-link {
            color: white;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .profile-pic-small {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        /* Main Content Styles */
        .container {
            max-width: 1200px;
            margin: 70px auto 20px;
            padding: 0 20px;
            display: flex;
            gap: 20px;
        }
        
        .sidebar {
            width: 25%;
            position: sticky;
            top: 70px;
            height: fit-content;
        }
        
        .main-content {
            width: 75%;
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        /* Post Styles */
        .post-form {
            margin-bottom: 20px;
        }
        
        .post-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 80px;
            margin-bottom: 10px;
            font-family: inherit;
        }
        
        .post-form button {
            background-color: #1877f2;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .post {
            margin-bottom: 20px;
        }
        
        .post-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .post-user-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }
        
        .post-user-info {
            display: flex;
            flex-direction: column;
        }
        
        .post-user-name {
            font-weight: bold;
        }
        
        .post-time {
            font-size: 12px;
            color: #65676b;
        }
        
        .post-content {
            margin-bottom: 10px;
        }
        
        .post-image {
            max-width: 100%;
            max-height: 500px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .post-actions {
            display: flex;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        .post-action {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-right: 15px;
            color: #65676b;
            font-size: 14px;
        }
        
        /* Profile Styles */
        .profile-banner {
            height: 300px;
            width: 100%;
            background-size: cover;
            background-position: center;
            position: relative;
            border-radius: 8px;
            margin-bottom: 70px;
        }
        
        .profile-pic-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid white;
            position: absolute;
            bottom: -75px;
            left: 20px;
            object-fit: cover;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .profile-info {
            margin-top: 90px;
        }
        
        .profile-name {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .profile-username {
            color: #65676b;
            margin-bottom: 15px;
        }
        
        .profile-description {
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .profile-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .profile-stat {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .profile-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #1877f2;
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: #166fe5;
        }
        
        .btn-secondary {
            background-color: #e4e6eb;
            color: #333;
            border: none;
        }
        
        .btn-secondary:hover {
            background-color: #d8dadf;
        }
        
        /* Minecraft Server Styles */
        .minecraft-server {
            background: #2c3e50;
            color: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .server-header {
            background: #1a252f;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .server-info {
            padding: 15px;
        }
        
        .server-status {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 10px;
        }
        
        .status-online {
            color: #2ecc71;
        }
        
        .status-offline {
            color: #e74c3c;
        }
        
        .server-ip-box {
            background: #34495e;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        .server-ip {
            font-family: monospace;
            font-size: 14px;
        }
        
        .copy-btn {
            background: #3498db;
            border: none;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .server-players {
            margin-top: 15px;
        }
        
        .player-list {
            margin-top: 10px;
        }
        
        .player {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            margin-bottom: 5px;
        }
        
        .player-skin {
            width: 32px;
            height: 32px;
            image-rendering: pixelated;
        }
        
        /* Form Styles */
        .form-container {
            max-width: 500px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 20px;
            color: #1877f2;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
        }
        
        .form-submit {
            width: 100%;
            padding: 12px;
            background-color: #1877f2;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            margin-top: 10px;
        }
        
        .form-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .form-error {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .form-success {
            color: #2ecc71;
            font-size: 14px;
            margin-top: 5px;
        }
        
        /* Animations */
        .animated-profile {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar, .main-content {
                width: 100%;
            }
            
            .profile-banner {
                height: 200px;
                margin-bottom: 60px;
            }
            
            .profile-pic-large {
                width: 100px;
                height: 100px;
                bottom: -50px;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Logged In Header -->
        <header class="header">
            <div class="logo">SocialApp</div>
            <nav class="nav">
                <a href="?page=home" class="nav-link <?php echo $current_page == 'home' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Beranda
                </a>
                <a href="?page=create" class="nav-link <?php echo $current_page == 'create' ? 'active' : ''; ?>">
                    <i class="fas fa-plus"></i> Tambahkan
                </a>
                <a href="?page=friends" class="nav-link <?php echo $current_page == 'friends' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Teman
                </a>
                <a href="?page=messages" class="nav-link <?php echo $current_page == 'messages' ? 'active' : ''; ?>">
                    <i class="fas fa-comments"></i> Chat
                </a>
                <a href="#minecraft" class="nav-link">
                    <i class="fas fa-gamepad"></i> Server Minecraft
                </a>
            </nav>
            <div class="user-menu">
                <img src="uploads/<?php echo $_SESSION['profile_pic'] ?? 'default.jpg'; ?>" class="profile-pic-small">
                <span><?php echo $_SESSION['username']; ?></span>
                <a href="?logout=1" style="margin-left: 10px;"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>
    <?php else: ?>
        <!-- Guest Header -->
        <header class="header">
            <div class="logo">SocialApp</div>
            <nav class="nav">
                <a href="#minecraft" class="nav-link">
                    <i class="fas fa-gamepad"></i> Server Minecraft
                </a>
            </nav>
            <div class="user-menu">
                <a href="?page=login" class="nav-link">Login</a>
                <a href="?page=register" class="nav-link">Register</a>
            </div>
        </header>
    <?php endif; ?>

    <div class="container">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <!-- Guest Content -->
            <?php if ($current_page == 'login'): ?>
                <!-- Login Form -->
                <div class="form-container">
                    <h2 class="form-title">Login</h2>
                    <?php if (isset($login_error)): ?>
                        <div class="form-error"><?php echo $login_error; ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="form-group">
                            <label for="username" class="form-label">Username atau Email</label>
                            <input type="text" id="username" name="username" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" class="form-input" required>
                        </div>
                        <input type="submit" name="login" value="Login" class="form-submit">
                        <div class="form-link">
                            Tidak punya akun? <a href="?page=register">Daftar disini</a>
                        </div>
                    </form>
                </div>
            <?php elseif ($current_page == 'register'): ?>
                <!-- Registration Form -->
                <div class="form-container">
                    <h2 class="form-title">Daftar Akun</h2>
                    <?php if (isset($registration_error)): ?>
                        <div class="form-error"><?php echo $registration_error; ?></div>
                    <?php endif; ?>
                    <?php if (isset($registration_success)): ?>
                        <div class="form-success"><?php echo $registration_success; ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="form-group">
                            <label for="full_name" class="form-label">Nama Lengkap</label>
                            <input type="text" id="full_name" name="full_name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" id="username" name="username" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" class="form-input" required>
                        </div>
                        <input type="submit" name="register" value="Daftar" class="form-submit">
                        <div class="form-link">
                            Sudah punya akun? <a href="?page=login">Login disini</a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Default Guest View (Minecraft Server Info) -->
                <div class="main-content">
                    <div class="minecraft-server">
                        <div class="server-header">
                            <i class="fas fa-server"></i>
                            <h3>Silence SMP Minecraft Server</h3>
                        </div>
                        <div class="server-info">
                            <div class="server-status">
                                <i class="fas fa-circle" id="server-status-icon"></i>
                                <span id="server-status-text">Memeriksa status server...</span>
                            </div>
                            
                            <h4>Java Edition</h4>
                            <div class="server-ip-box">
                                <span class="server-ip" id="java-ip"><?php echo $java_ip; ?></span>
                                <button class="copy-btn" onclick="copyToClipboard('java-ip')">
                                    <i class="fas fa-copy"></i> Salin
                                </button>
                            </div>
                            
                            <h4>Bedrock Edition</h4>
                            <div class="server-ip-box">
                                <span class="server-ip" id="bedrock-ip"><?php echo $bedrock_ip; ?></span>
                                <button class="copy-btn" onclick="copyToClipboard('bedrock-ip')">
                                    <i class="fas fa-copy"></i> Salin
                                </button>
                            </div>
                            <p>Port: <?php echo $bedrock_port; ?></p>
                            
                            <div class="server-players">
                                <h4>Pemain Online</h4>
                                <div id="online-count">0/20 pemain online</div>
                                <div class="player-list" id="player-list">
                                    <!-- Player list will be populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="margin-top: 20px; text-align: center;">
                        <h3>Bergabunglah dengan komunitas kami!</h3>
                        <p>Daftar sekarang untuk mengakses semua fitur sosial media dan bermain di server Minecraft kami.</p>
                        <a href="?page=register" class="btn btn-primary" style="display: inline-block; margin-top: 10px;">Daftar Sekarang</a>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Logged In Content -->
            <div class="sidebar">
                <?php if ($current_page == 'profile' && isset($profile)): ?>
                    <!-- Profile Sidebar -->
                    <div class="card">
                        <h3><?php echo $profile['full_name']; ?></h3>
                        <p>@<?php echo $profile['username']; ?></p>
                        
                        <?php if ($profile['minecraft_username']): ?>
                            <div style="margin: 15px 0;">
                                <h4>Minecraft Player</h4>
                                <div class="player">
                                    <img src="https://mc-heads.net/avatar/<?php echo $profile['minecraft_username']; ?>/32" 
                                         class="player-skin" alt="<?php echo $profile['minecraft_username']; ?>">
                                    <span><?php echo $profile['minecraft_username']; ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="profile-stat">
                            <i class="fas fa-users"></i>
                            <span><?php echo $friends_count; ?> Teman</span>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <?php if ($profile['id'] == $_SESSION['user_id']): ?>
                                <a href="?page=edit_profile" class="btn btn-primary" style="display: block; text-align: center;">
                                    <i class="fas fa-edit"></i> Edit Profile
                                </a>
                            <?php else: ?>
                                <a href="?page=add_friend&id=<?php echo $profile['id']; ?>" class="btn btn-primary" style="display: block; text-align: center;">
                                    <i class="fas fa-user-plus"></i> Tambah Teman
                                </a>
                                <a href="?page=messages&to=<?php echo $profile['id']; ?>" class="btn btn-secondary" style="display: block; text-align: center; margin-top: 10px;">
                                    <i class="fas fa-envelope"></i> Kirim Pesan
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Default Sidebar -->
                    <div class="card">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                            <img src="uploads/<?php echo $current_user['profile_pic'] ?? 'default.jpg'; ?>" 
                                 class="profile-pic-small <?php if (pathinfo($current_user['profile_pic'], PATHINFO_EXTENSION) === 'gif') echo 'animated-profile'; ?>">
                            <span><?php echo $current_user['full_name']; ?></span>
                        </div>
                        
                        <?php if ($current_user['minecraft_username']): ?>
                            <div style="margin: 15px 0;">
                                <h4>Minecraft Player</h4>
                                <div class="player">
                                    <img src="https://mc-heads.net/avatar/<?php echo $current_user['minecraft_username']; ?>/32" 
                                         class="player-skin" alt="<?php echo $current_user['minecraft_username']; ?>">
                                    <span><?php echo $current_user['minecraft_username']; ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Minecraft Server Info -->
                <div class="minecraft-server">
                    <div class="server-header">
                        <i class="fas fa-server"></i>
                        <h3>Silence SMP</h3>
                    </div>
                    <div class="server-info">
                        <div class="server-status">
                            <i class="fas fa-circle" id="server-status-icon"></i>
                            <span id="server-status-text">Memeriksa status server...</span>
                        </div>
                        
                        <h4>Java Edition</h4>
                        <div class="server-ip-box">
                            <span class="server-ip" id="java-ip"><?php echo $java_ip; ?></span>
                            <button class="copy-btn" onclick="copyToClipboard('java-ip')">
                                <i class="fas fa-copy"></i> Salin
                            </button>
                        </div>
                        
                        <h4>Bedrock Edition</h4>
                        <div class="server-ip-box">
                            <span class="server-ip" id="bedrock-ip"><?php echo $bedrock_ip; ?></span>
                            <button class="copy-btn" onclick="copyToClipboard('bedrock-ip')">
                                <i class="fas fa-copy"></i> Salin
                            </button>
                        </div>
                        <p>Port: <?php echo $bedrock_port; ?></p>
                        
                        <div class="server-players">
                            <h4>Pemain Online</h4>
                            <div id="online-count">0/20 pemain online</div>
                            <div class="player-list" id="player-list">
                                <!-- Player list will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="main-content">
                <?php if ($current_page == 'home'): ?>
                    <!-- Home Page - Post Feed -->
                    <div class="card post-form">
                        <form method="post" enctype="multipart/form-data">
                            <textarea name="content" placeholder="Apa yang kamu pikirkan?"></textarea>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <input type="file" id="post-image" name="image" accept="image/*" style="display: none;">
                                    <label for="post-image" style="cursor: pointer;">
                                        <i class="fas fa-image"></i> Foto
                                    </label>
                                </div>
                                <input type="submit" name="create_post" value="Posting" class="btn btn-primary">
                            </div>
                        </form>
                    </div>
                    
                    <?php if (!empty($posts)): ?>
                        <?php foreach ($posts as $post): ?>
                            <div class="card post">
                                <div class="post-header">
                                    <img src="uploads/<?php echo $post['profile_pic'] ?? 'default.jpg'; ?>" class="post-user-pic">
                                    <div class="post-user-info">
                                        <span class="post-user-name"><?php echo $post['full_name']; ?></span>
                                        <span class="post-time"><?php echo date('d M Y H:i', strtotime($post['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="post-content">
                                    <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                                </div>
                                <?php if ($post['image']): ?>
                                    <img src="uploads/<?php echo $post['image']; ?>" class="post-image">
                                <?php endif; ?>
                                <div class="post-actions">
                                    <a href="#" class="post-action">
                                        <i class="far fa-thumbs-up"></i> Suka
                                    </a>
                                    <a href="#" class="post-action">
                                        <i class="far fa-comment"></i> Komentar
                                    </a>
                                    <a href="#" class="post-action">
                                        <i class="fas fa-share"></i> Bagikan
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="card">
                            <p>Tidak ada postingan untuk ditampilkan. Mulailah dengan membuat postingan pertama Anda!</p>
                        </div>
                    <?php endif; ?>
                
                <?php elseif ($current_page == 'profile' && isset($profile)): ?>
                    <!-- Profile Page -->
                    <div class="profile-banner" style="background-image: url('uploads/<?php echo $profile['banner'] ?? 'default_banner.jpg'; ?>')">
                        <img src="uploads/<?php echo $profile['profile_pic'] ?? 'default.jpg'; ?>" 
                             class="profile-pic-large <?php if (pathinfo($profile['profile_pic'], PATHINFO_EXTENSION) === 'gif') echo 'animated-profile'; ?>">
                    </div>
                    
                    <div class="profile-info">
                        <h1 class="profile-name"><?php echo $profile['full_name']; ?></h1>
                        <p class="profile-username">@<?php echo $profile['username']; ?></p>
                        
                        <div class="profile-stats">
                            <div class="profile-stat">
                                <i class="fas fa-users"></i>
                                <span><?php echo $friends_count; ?> Teman</span>
                            </div>
                        </div>
                        
                        <?php if (!empty($profile['description'])): ?>
                            <div class="profile-description">
                                <h3>Tentang Saya</h3>
                                <p><?php echo nl2br(htmlspecialchars($profile['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <h2 style="margin: 20px 0 10px;">Postingan</h2>
                    
                    <?php if (!empty($profile_posts)): ?>
                        <?php foreach ($profile_posts as $post): ?>
                            <div class="card post">
                                <div class="post-header">
                                    <img src="uploads/<?php echo $profile['profile_pic'] ?? 'default.jpg'; ?>" class="post-user-pic">
                                    <div class="post-user-info">
                                        <span class="post-user-name"><?php echo $profile['full_name']; ?></span>
                                        <span class="post-time"><?php echo date('d M Y H:i', strtotime($post['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="post-content">
                                    <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                                </div>
                                <?php if ($post['image']): ?>
                                    <img src="uploads/<?php echo $post['image']; ?>" class="post-image">
                                <?php endif; ?>
                                <div class="post-actions">
                                    <a href="#" class="post-action">
                                        <i class="far fa-thumbs-up"></i> Suka
                                    </a>
                                    <a href="#" class="post-action">
                                        <i class="far fa-comment"></i> Komentar
                                    </a>
                                    <a href="#" class="post-action">
                                        <i class="fas fa-share"></i> Bagikan
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="card">
                            <p>Tidak ada postingan untuk ditampilkan.</p>
                        </div>
                    <?php endif; ?>
                
                <?php elseif ($current_page == 'edit_profile'): ?>
                    <!-- Edit Profile Page -->
                    <div class="card">
                        <h2>Edit Profile</h2>
                        <form method="post" enctype="multipart/form-data" style="margin-top: 20px;">
                            <div class="form-group">
                                <label for="full_name">Nama Lengkap</label>
                                <input type="text" id="full_name" name="full_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Deskripsi</label>
                                <textarea id="description" name="description" class="form-input" 
                                          rows="4"><?php echo htmlspecialchars($current_user['description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="minecraft_username">Username Minecraft</label>
                                <input type="text" id="minecraft_username" name="minecraft_username" class="form-input" 
                                       value="<?php echo htmlspecialchars($current_user['minecraft_username']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="profile_pic">Foto Profil</label>
                                <input type="file" id="profile_pic" name="profile_pic" class="form-input" accept="image/*">
                                <small>Format GIF didukung untuk animasi</small>
                                <div style="margin-top: 10px;">
                                    <p>Foto saat ini:</p>
                                    <img src="uploads/<?php echo $current_user['profile_pic'] ?? 'default.jpg'; ?>" 
                                         style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="banner">Banner Profil</label>
                                <input type="file" id="banner" name="banner" class="form-input" accept="image/*">
                                <div style="margin-top: 10px;">
                                    <p>Banner saat ini:</p>
                                    <img src="uploads/<?php echo $current_user['banner'] ?? 'default_banner.jpg'; ?>" 
                                         style="width: 100%; max-height: 150px; object-fit: cover; border-radius: 4px;">
                                </div>
                            </div>
                            
                            <input type="submit" name="update_profile" value="Simpan Perubahan" class="btn btn-primary">
                        </form>
                    </div>
                
                <?php elseif ($current_page == 'create'): ?>
                    <!-- Create Post Page -->
                    <div class="card">
                        <h2>Buat Postingan Baru</h2>
                        <form method="post" enctype="multipart/form-data" style="margin-top: 20px;">
                            <div class="form-group">
                                <textarea name="content" placeholder="Apa yang kamu pikirkan?" 
                                          style="width: 100%; min-height: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="image">Tambahkan Gambar</label>
                                <input type="file" id="image" name="image" class="form-input" accept="image/*">
                            </div>
                            
                            <input type="submit" name="create_post" value="Posting" class="btn btn-primary">
                        </form>
                    </div>
                
                <?php elseif ($current_page == 'friends'): ?>
                    <!-- Friends Page -->
                    <div class="card">
                        <h2>Teman</h2>
                        <p>Fitur daftar teman akan tersedia segera!</p>
                    </div>
                
                <?php elseif ($current_page == 'messages'): ?>
                    <!-- Messages Page -->
                    <div class="card">
                        <h2>Pesan</h2>
                        <p>Fitur pesan akan tersedia segera!</p>
                    </div>
                
                <?php else: ?>
                    <!-- Default Page -->
                    <div class="card">
                        <h2>Halaman Tidak Ditemukan</h2>
                        <p>Halaman yang Anda cari tidak ditemukan.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Function to copy text to clipboard
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;
            navigator.clipboard.writeText(text).then(() => {
                alert('Teks berhasil disalin!');
            });
        }
        
        // Function to fetch Minecraft server status
        async function fetchServerStatus() {
            try {
                const response = await fetch('https://api.mcsrvstat.us/2/silencesmps3.sytes.net:19133');
                const data = await response.json();
                
                const statusIcon = document.getElementById('server-status-icon');
                const statusText = document.getElementById('server-status-text');
                const onlineCount = document.getElementById('online-count');
                const playerList = document.getElementById('player-list');
                
                if (data.online) {
                    statusIcon.className = 'fas fa-circle status-online';
                    statusText.textContent = 'Online';
                    statusText.className = 'status-online';
                    
                    onlineCount.textContent = `${data.players.online}/${data.players.max} pemain online`;
                    
                    if (data.players.list && data.players.list.length > 0) {
                        playerList.innerHTML = '';
                        data.players.list.forEach(player => {
                            const playerElement = document.createElement('div');
                            playerElement.className = 'player';
                            playerElement.innerHTML = `
                                <img src="https://mc-heads.net/avatar/${player}/32" class="player-skin">
                                <span>${player}</span>
                            `;
                            playerList.appendChild(playerElement);
                        });
                    } else {
                        playerList.innerHTML = '<p>Tidak ada pemain online</p>';
                    }
                } else {
                    statusIcon.className = 'fas fa-circle status-offline';
                    statusText.textContent = 'Offline';
                    statusText.className = 'status-offline';
                    onlineCount.textContent = '0/0 pemain online';
                    playerList.innerHTML = '<p>Server sedang offline</p>';
                }
            } catch (error) {
                console.error('Error fetching server status:', error);
                document.getElementById('server-status-text').textContent = 'Gagal memeriksa status';
            }
        }
        
        // Fetch server status when page loads
        window.addEventListener('DOMContentLoaded', fetchServerStatus);
        
        // Refresh server status every 30 seconds
        setInterval(fetchServerStatus, 30000);
    </script>
</body>
</html>