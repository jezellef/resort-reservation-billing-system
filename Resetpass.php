<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/design.css">
    
    <title>Reset Password</title>
</head>
<body>
    <header>
        <div class="nav-links">
            <a href="landing.html" aria-label="User"  class="active"><img src="login-active.png" alt="User/Guest"></a>
            <a href="home-phase1.html">Home</a>
            <a href="accommodation-p1.html">Accommodations</a>
            <a href="#">Activities</a>
        </div>
        <div class="logo-container">
            <a href="#"><img src="rainbow-logo.png" alt="Rainbow Forest Logo"></a>
        </div>
        <div class="nav-links">
            <a href="aboutus.html">About Us</a>
            <a href="#">Contact Us</a>
            <a href="#" class="book-now">Book Now</a>
        </div>
    </header>
    <div class="login-container">
        <h2>Reset Password</h2>
        <div class="btn-group">
            <a href= "login.php" class="btn">LogIn</a>
            <a  href= "createacc.php"class="btn">Create New Account</a>
            <a href= "Resetpass.php" class="btn-active"><u>Reset your password</u></a>
        </div>

        <form>
            <div class="form-group">
                <br><label for="email">Email Address*</label>
                <input type="text" id="email" name="email" placeholder="Enter your Email Address">
            </div>
            
            <button type="submit" class="lbtn">Send Code</button>
            
            <div class="form-group">
                <br>
                <label for="password">Code*</label>
                <input type="password" id="password" name="fname" placeholder="The code was sent to your email">
            </div>

            <div class="form-group">
                <label for="password">New-Password*</label>
                <input type="password" id="password" name="sname" placeholder="Enter your password">
            </div>

            <div class="form-group">
                <label for="password">Confirm Password*</label>
                <input type="password" id="password" name="cnumber" placeholder="Confirm password">
            </div>

            <button type="submit" class="lbtn">LOG IN</button>
        </form>
    </div>

    
    <footer>
        <div class="footer-container">
            <div class="footer-logo">
                <img src="rainbow-logo.png" alt="Rainbow Forest Logo">
            </div>
            <div class="footer-nav">
                <h3>Explore</h3>
                <ul>
                    <li><a href="#">Accommodations</a></li>
                    <li><a href="#">Activities</a></li>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-contact">
                <h3>Contact Us</h3>
                <p><strong>Address:</strong> Brgy. Cuyambay, Tanay, Rizal</p>
                <p><strong>Contact No.:</strong> 0960 587 7561</p>
            </div>
            <div class="footer-actions">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="#">Follow Us</a></li>
                    <li><a href="#">Book Now</a></li>
                    <li><a href="#">Cancel Reservation</a></li>
                </ul>
            </div>
        </div>
</body>
</html>