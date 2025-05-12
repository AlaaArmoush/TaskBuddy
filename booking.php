<!DOCTYPE html>
<html lang="en">

<head>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="landing.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking</title>
</head>

<body>
<div class="container my-2">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card shadow-lg border-0" style="border-radius: 1.5rem; overflow: hidden;">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4">Book a Tasker</h2>
                    <form id="tasker-form">
                        <div class="mb-4">
                            <label for="date" class="form-label fw-bold">Date</label>
                            <input type="date" class="form-control" id="date" required>
                        </div>

                        <div class="mb-4">
                            <label for="time" class="form-label fw-bold">Preferred Time</label>
                            <select class="form-control" id="time" required>
                                <option value="" disabled selected>Select a time</option>
                                <option>Morning (8AM - 12PM)</option>
                                <option>Afternoon (12PM - 4PM)</option>
                                <option>Evening (4PM - 8PM)</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label fw-bold">Task Description</label>
                            <textarea class="form-control" id="description" rows="4" placeholder="Please describe what you need help with..." required></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="address" class="form-label fw-bold">Address</label>
                            <input type="text" class="form-control" id="address" placeholder="Enter your full address" required>
                        </div>

                        <div class="mb-4">
                            <label for="contact" class="form-label fw-bold">Contact Information</label>
                            <input type="text" class="form-control" id="contact" placeholder="Phone number or email" required>
                        </div>

                        <div class="text-center mt-5">
                            <button type="submit" class="btn btn-primary btn-lg">Book Now</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>

</body>
</html>