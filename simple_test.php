<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Simple Face Test</title>
    <style>
        body { background-color: #222; color: white; text-align: center; font-family: Arial; }
        video { border: 3px solid white; border-radius: 10px; width: 640px; height: 480px; }
        #status { font-size: 24px; margin-top: 20px; font-weight: bold; color: yellow; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
</head>
<body>

    <h1>👤 Simple Face Recognition Test</h1>
    <video id="video" autoplay muted></video>
    <p id="status">🔄 Loading models...</p>

    <script>
        const video = document.getElementById('video');
        const statusText = document.getElementById('status');
        let matcher = null;

        // 2. Set your model path (Ensure this is correct!)
        // If this file is in /attendance/, and models are in /attendance/models/, use "./models"
        const MODEL_URL = './models'; 

        // 3. Load Models
        Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
            faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL)
        ]).then(startCamera).catch(err => {
            console.error(err);
            statusText.textContent = "❌ Error: Check Console (F12). Files missing?";
            statusText.style.color = "red";
        });

        // 4. Start Camera
        function startCamera() {
            statusText.textContent = "📷 Starting Camera...";
            navigator.mediaDevices.getUserMedia({ video: {} })
                .then(stream => {
                    video.srcObject = stream;
                    loadDatabase(); // Start loading faces after camera starts
                })
                .catch(err => statusText.textContent = "❌ Camera Error: " + err);
        }

        // 5. Load Faces from Database
        async function loadDatabase() {
            statusText.textContent = "📡 Fetching Face Data...";
            
            try {
                const response = await fetch('get_all_face_descriptors.php');
                const users = await response.json();

                if (users.length === 0) {
                    statusText.textContent = "⚠️ No faces in database. Add a user first.";
                    return;
                }

                // Create the Matcher
                const labeledDescriptors = users.map(user => {
                    const descriptor = new Float32Array(user.descriptor);
                    return new faceapi.LabeledFaceDescriptors(user.label, [descriptor]);
                });

                matcher = new faceapi.FaceMatcher(labeledDescriptors, 0.55);
                statusText.textContent = "✅ Ready! Detecting...";
                statusText.style.color = "#0f0";
                
                startDetection();

            } catch (err) {
                console.error(err);
                statusText.textContent = "❌ Database Error (Check get_all_face_descriptors.php)";
                statusText.style.color = "red";
            }
        }

        // 6. Loop to Detect Faces
        function startDetection() {
            setInterval(async () => {
                if (!matcher) return;

                const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (detection) {
                    const result = matcher.findBestMatch(detection.descriptor);
                    
                    if (result.label !== 'unknown') {
                        statusText.textContent = "👋 Hello, " + result.label + "!";
                        statusText.style.color = "#0f0"; // Green
                    } else {
                        statusText.textContent = "❓ Unknown Face";
                        statusText.style.color = "yellow";
                    }
                }
            }, 500); // Check every 0.5 seconds
        }
    </script>

</body>
</html>