<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $description = $conn->real_escape_string($_POST['description']);
    $minecraft_username = $conn->real_escape_string($_POST['minecraft_username']);
    
    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $target_dir = "uploads/";
        $target_file = $target_dir . uniqid() . '.' . pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION);
        
        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            // Delete old profile picture if it's not the default
            if ($user['profile_pic'] != 'default.jpg') {
                unlink("uploads/" . $user['profile_pic']);
            }
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
            // Delete old banner if it's not the default
            if ($user['banner'] != 'default_banner.jpg') {
                unlink("uploads/" . $user['banner']);
            }
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
    
    if ($conn->query($sql)) {
        $_SESSION['full_name'] = $full_name;
        header("Location: profile.php");
    } else {
        echo "Error updating profile: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .edit-profile-form {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .preview-images {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .preview-box {
            text-align: center;
        }
        
        .preview-img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
        }
        
        .submit-btn {
            background: #1877f2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="edit-profile-form">
            <h2>Edit Profile</h2>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="full_name">Nama Lengkap</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="description">Deskripsi</label>
                    <textarea id="description" name="description"><?php echo htmlspecialchars($user['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="minecraftusername">Username Minecraft</label>
                    <input type="text" id="minecraftusername" name="minecraftusername" value="<?php echo htmlspecialchars($user['minecraft_username']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="profile_pic">Foto Profil (Bisa GIF)</label>
                    <input type="file" id="profile_pic" name="profile_pic" accept="image/*">
                    <div class="preview-images">
                        <div class="preview-box">
                            <p>Current:</p>
                            <img src="uploads/<?php echo $user['profile_pic']; ?>" class="preview-img">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="banner">Banner Profil</label>
                    <input type="file" id="banner" name="banner" accept="image/*">
                    <div class="preview-images">
                        <div class="preview-box">
                            <p>Current:</p>
                            <img src="uploads/<?php echo $user['banner']; ?>" class="preview-img">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">Simpan Perubahan</button>
            </form>
        </div>
    </div>
</body>
</html>