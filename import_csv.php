<?php
// import_csv.php
require_once "config.php";

if (!isset($_FILES['csv'])) {
    echo json_encode(["status" => "error", "message" => "No file uploaded"]);
    exit;
}

$file = $_FILES['csv']['tmp_name'];

if (($handle = fopen($file, "r")) !== false) {
    fgetcsv($handle); // skip header
    $count = 0;
    $duplicates = 0;
    $default_password = password_hash("@Spsfaculty123", PASSWORD_DEFAULT);

    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
        
        // 🟢 LINISIN ANG INVISIBLE CHARACTERS (BOM) GALING SA EXCEL
        $raw_name = preg_replace('/\xEF\xBB\xBF/', '', $data[0] ?? '');
        
        $name = trim($raw_name);
        $email = trim($data[1] ?? '');
        
        // 🟢 ROLE STANDARDIZATION: Gawing parehas sa Manual Add!
        $role = strtolower(trim($data[2] ?? 'user'));
        if ($role === 'faculty' || $role === 'teacher') {
            $role = 'user'; // I-force na maging 'user' sa database para gumana lahat ng functions
        }
        
        $position = trim($data[3] ?? '');
        $department = trim($data[4] ?? '');

        if (!$name || !$email) continue;

        // 🟢 VALIDATION: Check if Name or Email already exists to prevent double input
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR name = ?");
        $check->bind_param("ss", $email, $name);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $duplicates++; // Found a duplicate, skip this row
            $check->close();
            continue; 
        }
        $check->close();

        // 🟢 INSERT AS INACTIVE
        $stmt = $conn->prepare("
            INSERT INTO users (name, face_path, email, role, position, department, password, status)
            VALUES (?, NULL, ?, ?, ?, ?, ?, 'inactive')
        ");
        $stmt->bind_param("ssssss", $name, $email, $role, $position, $department, $default_password);
        
        if ($stmt->execute()) {
            $count++;
            
            // 🟢 SEND HTML EMAIL WITH ACTIVATION BUTTON
            $subject = "Activate Your Account - Faculty System";
            
            // 1. Set the activation link
            $activation_link = "https://sjpspanasahan.com/activate.php?email=" . urlencode($email);

            // 2. Create the HTML Message
            $message = "
            <html>
            <head>
              <title>Activate Your Account</title>
            </head>
            <body style='font-family: Arial, sans-serif; color: #1F4015;'>
                <h3>Welcome, $name!</h3>
                <p>An account has been created for you in the Faculty Management System.</p>
                <p>Your temporary password is: <strong>@Spsfaculty123</strong></p>
                <p>Please click the button below to activate your account so you can log in:</p>
                <br>
                <a href='$activation_link' style='background-color: #5D6F47; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Activate Account</a>
                <br><br>
                <p style='font-size: 12px; color: #666;'>If the button doesn't work, copy and paste this link into your browser:<br>$activation_link</p>
            </body>
            </html>
            ";
            
            // 3. Set the headers to accept HTML
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: system@sjpspanasahan.com" . "\r\n";
            
            // Send Email Silently
            @mail($email, $subject, $message, $headers); 
        }
        $stmt->close();
    }

    fclose($handle);
    
    // 🟢 DYNAMIC RESPONSES
    if ($count > 0) {
        $msg = "Imported $count users successfully and sent emails.";
        if ($duplicates > 0) $msg .= " ($duplicates duplicates were skipped).";
        echo json_encode(["status" => "success", "message" => $msg]);
    } elseif ($duplicates > 0) {
        echo json_encode(["status" => "error", "message" => "All users in this CSV already exist in the system!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "No valid data found in CSV."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Unable to read file"]);
}
?>