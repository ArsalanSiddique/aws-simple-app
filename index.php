<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// AWS S3 Configuration
$s3Config = [
    'region'  => 'us-east-1',
    'version' => 'latest',
    'credentials' => [
        'key'    => '',
        'secret' => '',
    ],
];
$s3Bucket = 'school-profile';
$s3Client = new S3Client($s3Config);

// Database Configuration
$dbHost = '';
$dbUser = '';
$dbPassword = '';
$dbName = '';

$conn = mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $photo = $_FILES['photo'];

    if ($photo['error'] === 0) {
        $photoName = time() . '-' . $photo['name'];
        $photoPath = $photo['tmp_name'];

        // Upload photo to S3
        try {
            $result = $s3Client->putObject([
                'Bucket' => $s3Bucket,
                'Key'    => $photoName,
                'SourceFile' => $photoPath,
                'ACL'    => 'public-read', // dont use if using ACL disabled (recommended)
            ]);
            $photoUrl = $result['ObjectURL'];

            // Insert data into RDS
            $sql = "INSERT INTO students (name, email, phone, photo_key, photo_url) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $phone, $photoName, $photoUrl);

            if (mysqli_stmt_execute($stmt)) {
                echo "Student added successfully!";
            } else {
                echo "Error: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } catch (AwsException $e) {
            echo "S3 Error: " . $e->getMessage();
        }
    } else {
        echo "Error uploading photo.";
    }
}

// Fetch Data from RDS
$sql = "SELECT * FROM students";
$result = mysqli_query($conn, $sql);
$students = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AWS Practice</title>
</head>
<body>
    <h1>Student Records</h1>
    <form method="POST" enctype="multipart/form-data">
        <label>Name: <input type="text" name="name" required></label><br>
        <label>Email: <input type="email" name="email" required></label><br>
        <label>Phone: <input type="text" name="phone" required></label><br>
        <label>Photo: <input type="file" name="photo" required></label><br>
        <button type="submit">Add Student</button>
    </form>

    <h2>Student List</h2>
    <table border="1">
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Photo</th>
            <th>Action</th>
        </tr>
        <?php foreach ($students as $student): ?>
        <tr>
            <td><?= htmlspecialchars($student['name']) ?></td>
            <td><?= htmlspecialchars($student['email']) ?></td>
            <td><?= htmlspecialchars($student['phone']) ?></td>
            <td><img src="<?= htmlspecialchars($student['photo_url']) ?>" alt="Photo" width="100"></td>
            <td>
                <a href="edit.php?id=<?= $student['id'] ?>"><button>EDIT</button></a>
                <form method="POST" action="delete.php">
                    <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                    <input type="submit" value="Delete">
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
