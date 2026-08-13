<?php
// ── DB CONFIG ── change these to match your Hostinger credentials
$db_host = 'localhost';
$db_name = 'u162817538_kma_website';
$db_user = 'u162817538_kma_admin';
$db_pass = 'Spaghetti.124';

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname']  ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $email     = trim($_POST['email']     ?? '');
    $subject   = trim($_POST['subject']   ?? '');
    $message   = trim($_POST['message']   ?? '');

    if (!$firstname || !$lastname || !$email || !$subject || !$message) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("
                INSERT INTO inquiries (firstname, lastname, phone, email, subject, message)
                VALUES (:firstname, :lastname, :phone, :email, :subject, :message)
            ");
            $stmt->execute([
                ':firstname' => $firstname,
                ':lastname'  => $lastname,
                ':phone'     => $phone,
                ':email'     => $email,
                ':subject'   => $subject,
                ':message'   => $message,
            ]);
            $success = true;
        } catch (PDOException $e) {
            $error = 'Something went wrong. Please try again later.';
            // Uncomment below line only for local testing:
            // $error = $e->getMessage();
        }
    }
}
?>
<?php include 'navbar.php'; ?>

<!-- Page Header -->
<section class="contact-hero text-white text-center py-5">
  <div class="container py-3">
    <h1 class="fw-bold">Contact Us</h1>
    <p class="text-white-50">Have questions? We'd love to hear from you.</p>
  </div>
</section>

<!-- Contact Section -->
<section class="py-5" style="background:#f4f7fb;">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-7">

        <!-- Success Message -->
        <?php if ($success): ?>
        <div class="alert alert-success rounded-3 text-center py-4 mb-4">
          <h5 class="fw-bold mb-1">✅ Inquiry Sent!</h5>
          <p class="mb-0">Thank you! Our team will get back to you shortly.</p>
        </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="contact-card rounded-4 p-4 p-md-5">

          <h4 class="fw-bold text-white mb-4">Send an Inquiry</h4>

          <div method="POST" action="contact.php" id="contactForm">

            <!-- Name Row -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label text-white fw-semibold">Firstname <span class="text-danger">*</span></label>
                <input type="text" name="firstname" class="form-control contact-input"
                  placeholder="Firstname"
                  value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label text-white fw-semibold">Lastname <span class="text-danger">*</span></label>
                <input type="text" name="lastname" class="form-control contact-input"
                  placeholder="Lastname"
                  value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>" required>
              </div>
            </div>

            <!-- Contact Number -->
            <div class="mb-3">
              <label class="form-label text-white fw-semibold">Contact Number</label>
              <input type="text" name="phone" class="form-control contact-input"
                placeholder="Number"
                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>

            <!-- Email -->
            <div class="mb-3">
              <label class="form-label text-white fw-semibold">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control contact-input"
                placeholder="Email"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <!-- Subject -->
            <div class="mb-3">
              <label class="form-label text-white fw-semibold">Subject <span class="text-danger">*</span></label>
              <select name="subject" class="form-select contact-input" required>
                <option value="" disabled <?= empty($_POST['subject']) ? 'selected' : '' ?>>Select subject</option>
                <?php
                  $subjects = [
                    'Taxation',
                    'Management Advisory',
                    'General Accounting',
                    'Attest Services',
                    'Business Registration',
                    'Paralegal Work For Lawyers',
                    'Others',
                  ];
                  foreach ($subjects as $s):
                    $sel = (($_POST['subject'] ?? '') === $s) ? 'selected' : '';
                ?>
                <option value="<?= $s ?>" <?= $sel ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Message -->
            <div class="mb-4">
              <label class="form-label text-white fw-semibold">Message <span class="text-danger">*</span></label>
              <textarea name="message" class="form-control contact-input" rows="5"
                placeholder="Write your message here..."
                required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>

            <!-- Submit -->
            <div class="text-center">
              <button type="button" onclick="submitForm()" class="btn btn-light px-5 py-2 rounded-pill fw-semibold contact-btn">
                Send Inquiry
              </button>
            </div>

          </div><!-- end form div -->
        </div><!-- end card -->
      </div>
    </div>
  </div>
</section>

<script>
function submitForm() {
  const form = document.getElementById('contactForm');
  // Basic validation
  const required = form.querySelectorAll('[required]');
  let valid = true;
  required.forEach(el => {
    if (!el.value.trim()) {
      el.classList.add('is-invalid');
      valid = false;
    } else {
      el.classList.remove('is-invalid');
    }
  });
  if (!valid) return;

  // Create a real form and submit it
  const realForm = document.createElement('form');
  realForm.method = 'POST';
  realForm.action = 'contact.php';
  const inputs = form.querySelectorAll('input, select, textarea');
  inputs.forEach(el => {
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = el.name;
    hidden.value = el.value;
    realForm.appendChild(hidden);
  });
  document.body.appendChild(realForm);
  realForm.submit();
}
</script>

<?php include 'footer.php'; ?>