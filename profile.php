<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get profile data
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['user_id'];
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
$posts = [];
if ($posts_result->num_rows > 0) {
    while ($row = $posts_result->fetch_assoc()) {
        $posts[] = $row;
    }
}

// Minecraft server info
$java_ip = "silencesmps3.sytes.net:19133";
$bedrock_ip = "silencesmps3.sytes.net";
$bedrock_port = "19133";
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $profile['full_name']; ?>'s Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Previous styles plus new ones */
        
        /* Profile Banner */
        .profile-banner {
            height: 300px;
            width: 100%;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .profile-pic-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid white;
            position: absolute;
            bottom: -75px;
            left: 50px;
            object-fit: cover;
        }
        
        .profile-info {
            margin-top: 90px;
            padding: 20px 50px;
        }
        
        .profile-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        /* Minecraft Server Info */
        .minecraft-server {
            background: #2c3e50;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .server-ip {
            background: #34495e;
            padding: 8px;
            border-radius: 4px;
            font-family: monospace;
            display: inline-block;
            margin-right: 10px;
        }
        
        .copy-btn {
            background: #3498db;
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .player-list {
            margin-top: 15px;
        }
        
        .player {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            background: rgba(255,255,255,0.1);
            padding: 8px;
            border-radius: 4px;
        }
        
        .player-skin {
            width: 32px;
            height: 32px;
            margin-right: 10px;
            image-rendering: pixelated;
        }
        
        /* Animation for GIF profile pictures */
        .animated-profile {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">SocialApp</div>
        <div class="nav">
            <a href="home.php" class="nav-link"><i class="fas fa-home"></i> Beranda</a>
            <a href="create_post.php" class="nav-link"><i class="fas fa-plus"></i> Tambahkan</a>
            <a href="friends.php" class="nav-link"><i class="fas fa-users"></i> Teman</a>
            <a href="messages.php" class="nav-link"><i class="fas fa-comments"></i> Chat</a>
            <a href="#minecraft" class="nav-link"><i class="fas fa-gamepad"></i> Server Minecraft</a>
        </div>
        <div class="user-menu">
            <img src="uploads/<?php echo $_SESSION['profile_pic'] ?? 'default.jpg'; ?>" class="profile-pic-small">
            <span><?php echo $_SESSION['username']; ?></span>
        </div>
    </div>
    
    <div class="profile-banner" style="background-image: url('uploads/<?php echo $profile['banner']; ?>')">
        <img src="uploads/<?php echo $profile['profile_pic']; ?>" 
             class="profile-pic-large <?php if (pathinfo($profile['profile_pic'], PATHINFO_EXTENSION) === 'gif') echo 'animated-profile'; ?>">
    </div>
    
    <div class="container">
        <div class="sidebar">
            <div class="profile-card">
                <h2><?php echo $profile['full_name']; ?></h2>
                <p>@<?php echo $profile['username']; ?></p>
                <p><i class="fas fa-users"></i> <?php echo $friends_count; ?> Teman</p>
                
                <?php if ($profile['minecraft_username']): ?>
                <div class="minecraft-info">
                    <h3>Minecraft Player</h3>
                    <img src="https://mc-heads.net/avatar/<?php echo $profile['minecraft_username']; ?>/32" 
                         class="player-skin" alt="<?php echo $profile['minecraft_username']; ?>">
                    <span><?php echo $profile['minecraft_username']; ?></span>
                </div>
                <?php endif; ?>
                
                <div class="profile-actions">
                    <?php if ($profile_id == $_SESSION['user_id']): ?>
                        <a href="edit_profile.php" class="button"><i class="fas fa-edit"></i> Edit Profile</a>
                    <?php else: ?>
                        <a href="add_friend.php?id=<?php echo $profile_id; ?>" class="button"><i class="fas fa-user-plus"></i> Tambah Teman</a>
                        <a href="messages.php?to=<?php echo $profile_id; ?>" class="button"><i class="fas fa-envelope"></i> Kirim Pesan</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="minecraft-server">
                <h3><i class="fas fa-server"></i> Minecraft Server</h3>
                <p>Status: <span id="server-status">Loading...</span></p>
                <p>Pemain online: <span id="online-players">0</span>/<span id="max-players">0</span></p>
                
                <h4>Java Edition</h4>
                <span class="server-ip" id="java-ip"><?php echo $java_ip; ?></span>
                <button class="copy-btn" onclick="copyToClipboard('java-ip')"><i class="fas fa-copy"></i> Salin</button>
                
                <h4>Bedrock Edition</h4>
                <span class="server-ip" id="bedrock-ip"><?php echo $bedrock_ip; ?></span>
                <button class="copy-btn" onclick="copyToClipboard('bedrock-ip')"><i class="fas fa-copy"></i> Salin</button>
                <p>Port: <?php echo $bedrock_port; ?></p>
                
                <div class="player-list" id="player-list">
                    <!-- Players will be loaded here by JavaScript -->
                </div>
            </div>
        </div>
        
        <div class="main-content">
            <div class="profile-description">
                <h3>Tentang Saya</h3>
                <p><?php echo nl2br(htmlspecialchars($profile['description'])); ?></p>
            </div>
            
            <!-- Posts section same as home.php -->
            <?php foreach ($posts as $post): ?>
            <div class="post">
                <!-- Post content same as before -->
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        // Function to copy IP to clipboard
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;
            navigator.clipboard.writeText(text).then(() => {
                alert('IP berhasil disalin!');
            });
        }
        
        // Fetch Minecraft server status
        async function fetchServerStatus() {
            try {
                const response = await fetch('https://api.mcsrvstat.us/2/silencesmps3.sytes.net:19133');
                const data = await response.json();
                
                if (data.online) {
                    document.getElementById('server-status').textContent = 'Online';
                    document.getElementById('server-status').style.color = 'lightgreen';
                    
                    document.getElementById('online-players').textContent = data.players.online;
                    document.getElementById('max-players').textContent = data.players.max;
                    
                    // Display players
                    const playerList = document.getElementById('player-list');
                    if (data.players.list) {
                        playerList.innerHTML = '<h4>Pemain Online:</h4>';
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
                    document.getElementById('server-status').textContent = 'Offline';
                    document.getElementById('server-status').style.color = 'red';
                    document.getElementById('player-list').innerHTML = '<p>Server sedang offline</p>';
                }
            } catch (error) {
                console.error('Error fetching server status:', error);
                document.getElementById('server-status').textContent = 'Error loading status';
            }
        }
        
        // Call the function when page loads
        window.onload = fetchServerStatus;
    </script>
</body>
</html>
