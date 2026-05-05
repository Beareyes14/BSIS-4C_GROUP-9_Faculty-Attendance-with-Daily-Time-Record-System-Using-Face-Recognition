<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$res = $conn->query("SELECT name, email, role, position, department, face_path FROM users WHERE id=$user_id");
$user = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Profile</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
/* General page styling */
body {
  background-color: #F9F7E8;
  font-family: Arial, sans-serif;
  color: #1F4015;
  margin: 0;
  padding: 0;
}
.content {
  margin-left: 270px;
  padding: 25px 40px;
  display: flex;
  flex-direction: column;
  align-items: center;
}
h1 {
  text-align: center;
  color: #1F4015;
  margin-bottom: 25px;
  font-size: 28px;
}

/* Grid layout */
.profile-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
  gap: 30px;
  width: 100%;
  max-width: 900px;
}

/* Cards */
.card {
  background-color: #AAB396;
  border: 2px solid #1F4015;
  border-radius: 12px;
  padding: 30px;
  color: #1F4015;
  box-shadow: 3px 3px 10px rgba(31, 64, 21, 0.2);
}

.card h3 {
  margin-top: 0;
  text-align: center;
  font-size: 20px;
  border-bottom: 2px solid #1F4015;
  padding-bottom: 8px;
  margin-bottom: 15px;
}

/* Profile picture styling */
.profile-pic {
  text-align: center;
  margin-bottom: 15px;
}
.profile-pic img {
  width: 130px;
  height: 130px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #1F4015;
  background: #fff;
}

/* Camera Buttons */
.camera-btn {
    background-color: #1F4015; 
    color: white; 
    padding: 8px 15px;
    font-size: 14px;
    margin-top: 10px;
    width: auto;
    display: inline-block;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
.camera-btn:hover { background-color: #3e5c32; }

/* 🟢 UPDATED: Camera Container (Removed Black Background) */
#camera-container {
    display: none;
    text-align: center;
    margin-top: 15px;
    /* Removed background: #000; */
    /* Removed padding: 10px; */
}

video {
    width: 100%;
    max-width: 320px;
    border-radius: 50%; /* Optional: Makes the video feed circle like the profile pic */
    aspect-ratio: 1 / 1; /* Forces square/circle ratio */
    object-fit: cover;
    transform: scaleX(-1); /* Mirror effect */
    border: 3px solid #1F4015; /* Green border to match theme */
}

/* Form elements */
label { display: block; font-weight: bold; margin-top: 12px; }
input {
  width: 100%; padding: 8px; padding-right: 40px;
  background-color: #F4DD97; border: 1px solid #EEC752;
  border-radius: 6px; color: #1F4015; margin-top: 5px; box-sizing: border-box;
}
input:disabled { background-color: #eae5c8; }

/* Buttons */
button {
  background-color: #EEC752; border: 1px solid #1F4015;
  border-radius: 6px; color: #1F4015; padding: 10px 18px;
  font-weight: bold; cursor: pointer; margin-top: 20px;
  transition: 0.3s; width: 100%;
}
button:hover { background-color: #D6B540; }

/* Password Styles */
.input-group { position: relative; width: 100%; }
.toggle-eye { position: absolute; right: 10px; top: 38px; cursor: pointer; color: #1F4015; z-index: 2; }
.validation-list { list-style: none; padding: 10px; margin-top: 10px; font-size: 13px; background: rgba(255,255,255,0.3); border-radius: 5px; }
.validation-list li { margin-bottom: 4px; display: flex; align-items: center; gap: 8px; font-weight: bold; }
.valid { color: #006400; } 
.invalid { color: #8B0000; } 
.valid::before { content: "✅"; }
.invalid::before { content: "❌"; }

@media (max-width: 850px) { .profile-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<?php
if ($_SESSION["role"] === "admin") {
    include "sidebar.php";
} elseif ($_SESSION["role"] === "user") {
    include "user_sidebar.php";
}
?>

<div class="content">
  <h1>My Profile</h1>

  <div class="profile-grid">
    <div class="card">
      <h3>Personal Information</h3>
      
      <div class="profile-pic">
        <img src="<?= $user['face_path'] ?: 'images/default_face.jpg' ?>?v=<?= time() ?>" id="preview">
        <br>
        <button type="button" class="camera-btn" onclick="openCamera()">📷 Take New Photo</button>
        
        <div id="camera-container">
            <video id="video" autoplay playsinline></video>
            <br>
            <button type="button" class="camera-btn" onclick="capturePhoto()" style="background:#EEC752; color:#1F4015; border:1px solid #1F4015;">Capture</button>
            <button type="button" class="camera-btn" onclick="stopCamera()" style="background:#8B0000; border:1px solid #5a0000;">Cancel</button>
            <canvas id="canvas" style="display:none;"></canvas>
        </div>
      </div>

      <input type="hidden" id="captured_image">
      <input type="hidden" id="face_descriptor">

      <label>Name:</label>
      <input type="text" id="name" value="<?= htmlspecialchars($user['name']) ?>">

      <label>Email:</label>
      <input type="email" id="email" value="<?= htmlspecialchars($user['email']) ?>">

      <label>Role:</label>
      <input type="text" value="<?= $user['role'] === 'user' ? 'Faculty' : ucfirst($user['role']) ?>" disabled>

      <label>Department:</label>
      <input type="text" value="<?= htmlspecialchars($user['department']) ?>" disabled>

      <label>Position:</label>
      <input type="text" value="<?= htmlspecialchars($user['position']) ?>" disabled>

      <button onclick="saveProfile()">Save Changes</button>
    </div>

    <div class="card">
      <h3>Change Password</h3>
      <div class="input-group">
          <label>New Password:</label>
          <input type="password" id="newPass">
          <i class="fa-solid fa-eye toggle-eye" onclick="togglePass('newPass', this)"></i>
      </div>

      <ul class="validation-list">
          <li id="req-len" class="invalid">At least 6 characters</li>
          <li id="req-upper" class="invalid">At least 1 Uppercase Letter</li>
          <li id="req-num" class="invalid">At least 1 Number</li>
          <li id="req-sym" class="invalid">At least 1 Symbol (!@#$%)</li>
      </ul>

      <div class="input-group">
          <label>Confirm New Password:</label>
          <input type="password" id="confirmPass">
          <i class="fa-solid fa-eye toggle-eye" onclick="togglePass('confirmPass', this)"></i>
      </div>

      <button onclick="changePassword()" id="changePassBtn">Change Password</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<script>
let cameraStream = null;
let modelsLoaded = false;

Promise.all([
    faceapi.nets.tinyFaceDetector.loadFromUri('./models'),
    faceapi.nets.faceLandmark68Net.loadFromUri('./models'),
    faceapi.nets.faceRecognitionNet.loadFromUri('./models')
]).then(() => {
    modelsLoaded = true;
    console.log("Models Loaded");
});

function openCamera() {
    document.getElementById('camera-container').style.display = 'block';
    // Hide current preview to avoid clutter
    document.getElementById('preview').style.display = 'none';
    
    navigator.mediaDevices.getUserMedia({ video: {} })
        .then(stream => {
            const video = document.getElementById('video');
            video.srcObject = stream;
            cameraStream = stream;
        })
        .catch(err => Swal.fire("Error", "Could not access camera: " + err, "error"));
}

async function capturePhoto() {
    if (!modelsLoaded) {
        Swal.fire("Wait", "AI Models are still loading...", "info");
        return;
    }

    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    const dataURL = canvas.toDataURL('image/jpeg');
    
    document.getElementById('preview').src = dataURL;
    document.getElementById('preview').style.display = 'inline-block'; // Show preview again
    document.getElementById('captured_image').value = dataURL;

    // Detect Face & Get Descriptor
    const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
        .withFaceLandmarks()
        .withFaceDescriptor();

    if (detection) {
        const descriptorArray = Array.from(detection.descriptor);
        document.getElementById("face_descriptor").value = JSON.stringify(descriptorArray);
        
        Swal.fire({
            icon: 'success', 
            title: 'Photo Taken', 
            text: 'Face data updated successfully!',
            timer: 1500,
            showConfirmButton: false
        });
        stopCamera();
    } else {
        Swal.fire({
            icon: 'warning', 
            title: 'No Face Detected', 
            text: 'Photo taken, but AI could not see a face clearly. Face Login might fail.'
        });
        stopCamera();
    }
}

function stopCamera() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
    document.getElementById('camera-container').style.display = 'none';
    document.getElementById('preview').style.display = 'inline-block'; // Ensure preview is visible if cancelled
}

function saveProfile() {
  const formData = new FormData();
  formData.append("name", document.getElementById("name").value);
  formData.append("email", document.getElementById("email").value);
  
  const capturedImage = document.getElementById("captured_image").value;
  if (capturedImage) {
      formData.append("captured_image", capturedImage);
  }
  
  const descriptor = document.getElementById("face_descriptor").value;
  if (descriptor) {
      formData.append("face_descriptor", descriptor);
  }

  fetch("update_profile.php", { method: "POST", body: formData })
  .then(r => r.json())
  .then(d => {
    // 🟢 UPDATED ALERT LOGIC
    Swal.fire({
      icon: d.status, // Can be 'success', 'error', or 'info'
      title: d.status === "success" ? "Updated!" : (d.status === "info" ? "Check Email" : "Error"),
      text: d.message,
      confirmButtonColor: "#1F4015"
    }).then(() => {
        // Only reload if fully successful (not waiting for email)
        if(d.status === 'success') location.reload();
    });
  });
}

function togglePass(inputId, icon) {
    const input = document.getElementById(inputId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

const newPassInput = document.getElementById('newPass');
let isPasswordValid = false;

if(newPassInput){
    newPassInput.addEventListener('keyup', function() {
        const val = newPassInput.value;
        const hasLen = val.length >= 6;
        const hasUpper = /[A-Z]/.test(val);
        const hasNum = /[0-9]/.test(val);
        const hasSym = /[!@#$%^&*(),.?":{}|<>]/.test(val);

        function updateReq(id, isValid) {
            const el = document.getElementById(id);
            if(isValid) { el.classList.remove('invalid'); el.classList.add('valid'); } 
            else { el.classList.remove('valid'); el.classList.add('invalid'); }
        }

        updateReq('req-len', hasLen);
        updateReq('req-upper', hasUpper);
        updateReq('req-num', hasNum);
        updateReq('req-sym', hasSym);

        isPasswordValid = hasLen && hasUpper && hasNum && hasSym;
    });
}

function changePassword() {
  const newPass = document.getElementById("newPass").value;
  const confirmPass = document.getElementById("confirmPass").value;

  if (newPass === "" || confirmPass === "") {
      Swal.fire({icon: 'warning', title: 'Missing Input', text: 'Please fill in both fields.', confirmButtonColor: "#1F4015"});
      return;
  }
  if (!isPasswordValid) {
      Swal.fire({icon: 'error', title: 'Weak Password', text: 'Password requirements not met.', confirmButtonColor: "#1F4015"});
      return;
  }
  if (newPass !== confirmPass) {
      Swal.fire({icon: 'error', title: 'Mismatch', text: 'Passwords do not match.', confirmButtonColor: "#1F4015"});
      return;
  }

  const data = new URLSearchParams();
  data.append("newpass", newPass);
  data.append("confirm", confirmPass);

  fetch("update_password.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: data
  })
  .then(r => r.json())
  .then(d => {
    Swal.fire({
      icon: d.status,
      title: d.status === "success" ? "Success" : "Error",
      text: d.message,
      confirmButtonColor: "#1F4015"
    }).then(() => {
      if (d.status === "success") {
        document.getElementById("newPass").value = "";
        document.getElementById("confirmPass").value = "";
        document.querySelectorAll('.validation-list li').forEach(li => {
            li.classList.remove('valid'); li.classList.add('invalid');
        });
        isPasswordValid = false;
      }
    });
  })
  .catch(err => console.error(err));
}
</script>
</body>
</html>