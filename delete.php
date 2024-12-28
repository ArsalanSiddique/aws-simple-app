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

$studentId = $_POST['student_id']; // Get the student ID from the POST request

// Fetch student details (for file path in S3)
$sql = "SELECT * FROM students WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Delete file from S3
$s3Client = new S3Client([
    'region'  => 'us-east-1',
    'version' => 'latest',
    'debug' => false,    
    'credentials' => [
        'key'    => '',
        'secret' => '',
    ],
]);

$bucketName = 'school-profile';
$fileKey = $student['photo_key']; // File path in S3
try {
    // Delete the file from S3
    $s3Client->deleteObject([
        'Bucket' => $bucketName,
        'Key'    => $fileKey,
    ]);
    echo "File deleted successfully from S3.<br>";

} catch (AwsException $e) {
    echo "Error deleting file from S3: " . $e->getMessage() . "<br>";
}

// Delete student record from MySQL database
$sql = "DELETE FROM students WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $studentId);

if ($stmt->execute()) {
    echo "Student record deleted successfully from database.";
} else {
    echo "Error deleting student record: " . $stmt->error;
}

$stmt->close();
$conn->close();


// Redirect to index.php after the delete operation
header("Location: index.php");
exit;

?>
