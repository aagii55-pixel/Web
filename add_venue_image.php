<?php
session_start();
require 'config/db.php';

// Ensure only managers can access this page
function ensureManagerAccess() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'Manager') {
        header('Location: ../login.php');
        exit();
    }
}

// Enforce role check
ensureManagerAccess();

$managerID = $_SESSION['user_id'];
$venueID = $_GET['venue_id'] ?? null;

// Check if the venue belongs to the manager
$checkSql = "SELECT VenueID, Name FROM Venue WHERE VenueID = ? AND ManagerID = ?";
$stmt = $conn->prepare($checkSql);
$stmt->bind_param("ii", $venueID, $managerID);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "<script>alert('Invalid venue or access denied.'); window.location.href = 'manager_dashboard.php';</script>";
    exit();
}
$venueInfo = $result->fetch_assoc();
$venueName = $venueInfo['Name'];
$stmt->close();

// Handle image upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['images'])) {
    $targetDir = "uploads/venue_images/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    // Start transaction for multiple image uploads
    $conn->begin_transaction();
    $uploadCount = 0;
    $failedCount = 0;
    
    try {
        foreach ($_FILES['images']['name'] as $key => $imageName) {
            // Skip empty files
            if (empty($imageName)) continue;
            
            // Generate a unique filename to prevent overwriting
            $fileExtension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
            $newFileName = $venueID . '_' . uniqid() . '.' . $fileExtension;
            $targetFilePath = $targetDir . $newFileName;
            
            // Check if the file is an allowed image type
            if (in_array($fileExtension, ["jpg", "jpeg", "png", "gif"])) {
                // Check file size (limit to 5MB)
                if ($_FILES['images']['size'][$key] <= 5000000) {
                    if (move_uploaded_file($_FILES['images']['tmp_name'][$key], $targetFilePath)) {
                        $stmtImage = $conn->prepare("INSERT INTO VenueImages (VenueID, ImagePath) VALUES (?, ?)");
                        $stmtImage->bind_param("is", $venueID, $targetFilePath);
                        $stmtImage->execute();
                        $stmtImage->close();
                        $uploadCount++;
                    } else {
                        $failedCount++;
                    }
                } else {
                    $failedCount++;
                }
            } else {
                $failedCount++;
            }
        }
        
        $conn->commit();
        
        if ($uploadCount > 0) {
            $message = "$uploadCount images uploaded successfully!";
            if ($failedCount > 0) {
                $message .= " $failedCount images failed to upload.";
            }
            echo "<script>alert('$message'); window.location.href = 'add_venue_image.php?venue_id=$venueID';</script>";
        } else if ($failedCount > 0) {
            echo "<script>alert('Failed to upload images. Please try again.'); window.location.href = 'add_venue_image.php?venue_id=$venueID';</script>";
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error occurred: " . $e->getMessage() . "'); window.location.href = 'add_venue_image.php?venue_id=$venueID';</script>";
    }
}

// Handle image deletion
if (isset($_GET['delete_image_id'])) {
    $imageID = $_GET['delete_image_id'];
    
    // Fetch the image path to delete the file
    $stmtImage = $conn->prepare("SELECT ImagePath FROM VenueImages WHERE ImageID = ? AND VenueID = ?");
    $stmtImage->bind_param("ii", $imageID, $venueID);
    $stmtImage->execute();
    $stmtImage->bind_result($imagePath);
    $stmtImage->fetch();
    $stmtImage->close();

    // Delete the image record from the database
    $stmtDelete = $conn->prepare("DELETE FROM VenueImages WHERE ImageID = ?");
    $stmtDelete->bind_param("i", $imageID);
    if ($stmtDelete->execute()) {
        // Delete the image file from the directory
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        echo "<script>alert('Image deleted successfully!'); window.location.href = 'add_venue_image.php?venue_id=$venueID';</script>";
    } else {
        echo "<script>alert('Error deleting image');</script>";
    }
    $stmtDelete->close();
}

// Fetch existing images
$stmtImages = $conn->prepare("SELECT ImageID, ImagePath FROM VenueImages WHERE VenueID = ?");
$stmtImages->bind_param("i", $venueID);
$stmtImages->execute();
$resultImages = $stmtImages->get_result();
$existingImages = $resultImages->fetch_all(MYSQLI_ASSOC);
$stmtImages->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Venue Images</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        h2, h3 {
            color: #333;
            text-align: center;
        }
        .upload-section {
            margin: 30px 0;
            text-align: center;
        }
        .existing-images, .preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .image-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            height: 200px;
        }
        .image-item:hover {
            transform: scale(1.03);
        }
        .image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .delete-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(220, 53, 69, 0.8);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s;
        }
        .delete-btn:hover {
            background-color: rgba(220, 53, 69, 1);
        }
        
        /* File Input Styling */
        .file-input-container {
            position: relative;
            margin: 20px auto;
            width: 300px;
        }
        .file-input-button {
            display: block;
            padding: 15px 25px;
            background-color: #007bff;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-align: center;
            transition: background-color 0.3s;
        }
        .file-input-button:hover {
            background-color: #0056b3;
        }
        .hidden-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        /* Upload Button Styling */
        .upload-btn {
            display: block;
            width: 200px;
            padding: 15px 25px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 20px auto;
            transition: background-color 0.3s;
        }
        .upload-btn:hover {
            background-color: #218838;
        }
        .upload-btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        
        /* Preview Section */
        .preview-section {
            margin: 30px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #ccc;
        }
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .preview-title {
            margin: 0;
            color: #495057;
        }
        .preview-count {
            background-color: #007bff;
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 14px;
        }
        
        /* Back Button */
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            transition: background-color 0.3s;
        }
        .back-btn:hover {
            background-color: #5a6268;
            text-decoration: none;
            color: white;
        }
        
        /* Additional Styling */
        .venue-name {
            color: #007bff;
            font-weight: bold;
        }
        .text-center {
            text-align: center;
        }
        .mt-3 {
            margin-top: 15px;
        }
        .info-text {
            color: #6c757d;
            font-size: 14px;
            text-align: center;
            margin: 10px 0;
        }
        .drop-zone {
            padding: 40px;
            text-align: center;
            border: 3px dashed #ccc;
            border-radius: 8px;
            margin: 20px 0;
            transition: all 0.3s;
        }
        .drop-zone.active {
            background-color: #e6f4ff;
            border-color: #007bff;
        }
        .drop-zone-text {
            font-size: 16px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Manage Images for <span class="venue-name"><?php echo htmlspecialchars($venueName); ?></span></h2>
        
        <div class="upload-section">
            <form id="uploadForm" action="add_venue_image.php?venue_id=<?php echo $venueID; ?>" method="post" enctype="multipart/form-data">
                <div class="drop-zone" id="dropZone">
                    <p class="drop-zone-text">Drag & drop images here or click to browse</p>
                    <div class="file-input-container">
                        <label class="file-input-button">Select Images
                            <input type="file" name="images[]" id="imageInput" class="hidden-input" multiple accept="image/*" onchange="handleFileSelect(this.files)">
                        </label>
                    </div>
                </div>
                
                <div class="preview-section" id="previewSection" style="display: none;">
                    <div class="preview-header">
                        <h3 class="preview-title">Selected Images</h3>
                        <span class="preview-count" id="selectedCount">0 images</span>
                    </div>
                    <div class="preview-container" id="previewContainer"></div>
                    <p class="info-text">Click on an image to remove it from selection</p>
                </div>
                
                <button type="submit" id="uploadButton" class="upload-btn" disabled>Upload Images</button>
            </form>
        </div>
        
        <h3>Existing Images</h3>
        <div class="existing-images">
            <?php if (count($existingImages) > 0): ?>
                <?php foreach ($existingImages as $image): ?>
                    <div class="image-item">
                        <img src="<?php echo htmlspecialchars($image['ImagePath']); ?>" alt="Venue Image">
                        <a href="add_venue_image.php?venue_id=<?php echo $venueID; ?>&delete_image_id=<?php echo $image['ImageID']; ?>" 
                           class="delete-btn" 
                           onclick="return confirm('Are you sure you want to delete this image?');">×</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center" style="grid-column: 1 / -1;">No images uploaded yet.</p>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-3">
            <a href="manager_dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>
    </div>

    <script>
        // Variables to store selected files
        let selectedFiles = [];
        
        // Handle file selection
        function handleFileSelect(files) {
            // Combine new files with existing ones
            const newFiles = Array.from(files);
            
            // Add new files to our array
            newFiles.forEach(file => {
                // Only add if it's an image
                if (file.type.startsWith('image/')) {
                    selectedFiles.push(file);
                }
            });
            
            // Update preview
            updatePreview();
        }
        
        // Update the preview container with selected images
        function updatePreview() {
            const previewContainer = document.getElementById('previewContainer');
            const previewSection = document.getElementById('previewSection');
            const selectedCount = document.getElementById('selectedCount');
            const uploadButton = document.getElementById('uploadButton');
            
            // Clear the current preview
            previewContainer.innerHTML = '';
            
            // Update count
            selectedCount.textContent = `${selectedFiles.length} image${selectedFiles.length !== 1 ? 's' : ''}`;
            
            // Show/hide preview section and enable/disable upload button
            if (selectedFiles.length > 0) {
                previewSection.style.display = 'block';
                uploadButton.disabled = false;
                
                // Create preview elements
                selectedFiles.forEach((file, index) => {
                    const imageDiv = document.createElement('div');
                    imageDiv.className = 'image-item';
                    
                    // Create image preview
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    
                    // Create delete button
                    const deleteBtn = document.createElement('button');
                    deleteBtn.className = 'delete-btn';
                    deleteBtn.innerHTML = '×';
                    deleteBtn.onclick = function(e) {
                        e.preventDefault();
                        removeImage(index);
                    };
                    
                    // Add to preview
                    imageDiv.appendChild(img);
                    imageDiv.appendChild(deleteBtn);
                    previewContainer.appendChild(imageDiv);
                });
            } else {
                previewSection.style.display = 'none';
                uploadButton.disabled = true;
            }
        }
        
        // Remove an image from the selection
        function removeImage(index) {
            selectedFiles.splice(index, 1);
            updatePreview();
            
            // Also update the file input if possible
            updateFileInput();
        }
        
        // Update the file input to match our selection
        function updateFileInput() {
            const input = document.getElementById('imageInput');
            
            // Create a DataTransfer object
            const dataTransfer = new DataTransfer();
            
            // Add all files in our array to the DataTransfer object
            selectedFiles.forEach(file => {
                dataTransfer.items.add(file);
            });
            
            // Set the new files to the input
            input.files = dataTransfer.files;
        }
        
        // Set up drag and drop functionality
        const dropZone = document.getElementById('dropZone');
        
        // Add event listeners for drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // Highlight drop zone when file is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropZone.classList.add('active');
        }
        
        function unhighlight() {
            dropZone.classList.remove('active');
        }
        
        // Handle file drop
        dropZone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFileSelect(files);
        }
        
        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            if (selectedFiles.length === 0) {
                e.preventDefault();
                alert('Please select at least one image to upload.');
            }
        });
    </script>
</body>
</html>