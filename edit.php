<?php

error_reporting(E_ALL);
require 'vendor/autoload.php'; // Ensure AWS SDK is installed via Composer

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// MySQL connection parameters
$servername = '';
$username = '';
$password = '';
$dbname = '';

// Create MySQL connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$s3Client = new S3Client([
    'region'  => 'us-east-1',
    'version' => 'latest',
    'debug' => false,    
    'credentials' => [
        'key'    => '',
        'secret' => '',
    ],
]);

// Fetch record based on ID passed in the URL (e.g., edit.php?id=1)
if (isset($_GET['id'])) {
    $student_id = $_GET['id'];

    // Fetch the student record from RDS
    $sql = "SELECT * FROM students WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    
    // Handle form submission (update)
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $photo = $_FILES['photo'];

        // Check if a new file is uploaded
        if ($photo['error'] === UPLOAD_ERR_OK) {
            // Delete old image from S3 if there's a new one
            if (!empty($student['photo_key'])) {
                $s3Client->deleteObject([
                    'Bucket' => 'school-profile',
                    'Key' => $student['photo_key']
                ]);
            }

            // Upload new image to S3
            $new_photo_key = time() . '-' . basename($photo['name']);
            $s3Client->putObject([
                'Bucket' => 'school-profile',
                'Key' => $new_photo_key,
                'SourceFile' => $photo['tmp_name'],
                'ACL' => 'public-read',
            ]);
            $photo_url = '' . $new_photo_key;
        } else {
            // If no file is uploaded, keep the old photo_url
            $photo_url = $student['photo_url'];
            $new_photo_key = $student['photo_key'];
        }

        // Update student record in the database
        $update_sql = "UPDATE students SET name = ?, email = ?, phone = ?, photo_key = ?, photo_url = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('sssssi', $name, $email, $phone, $new_photo_key, $photo_url, $student_id);
        
        if ($stmt->execute()) {
            header('Location: index.php'); // Redirect after update
        } else {
            echo "Error updating record: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student</title>
</head>
<body>
    <a href="index.php"><button>Back</button></a>
    <h1>Edit Student</h1>
    <form method="POST" enctype="multipart/form-data">
        <label>Name: <input type="text" name="name" value="<?= htmlspecialchars($student['name']) ?>" required></label><br>
        <label>Email: <input type="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" required></label><br>
        <label>Phone: <input type="text" name="phone" value="<?= htmlspecialchars($student['phone']) ?>" required></label><br>
        
        <label>Current Photo:</label><br>
        <img src="<?= htmlspecialchars($student['photo_url']) ?>" alt="Current Photo" width="100"><br>
        
        <label>New Photo (Optional): <input type="file" name="photo"></label><br>
        
        <button type="submit">Update Student</button>
    </form>
</body>
</html>
