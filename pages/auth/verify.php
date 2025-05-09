<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$base_url = '../../';

require_once '../../includes/header.php';
require_once '../../includes/functions.php';

if(!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_email'])) {
    setMessage("Session expired. Please register again.", "error");
    header("Location: register.php");
    exit;
}

$user_id = $_SESSION['temp_user_id'];
$email = $_SESSION['temp_email'];

error_log("Verification page loaded for user_id: $user_id, email: $email");

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $verification_code = sanitizeInput($_POST['verification_code']);
    
    if(empty($verification_code)) {
        $error = "Verification code is required";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND verification_code = ?");
            $stmt->execute([$user_id, $verification_code]);
            
            if($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
                
                if($stmt->execute([$user_id])) {
                    $_SESSION['user_id'] = $user_id;
                    
                    unset($_SESSION['temp_user_id']);
                    unset($_SESSION['temp_email']);
                    
                    setMessage("Your email has been verified successfully. Welcome to the Business Card Creator!", "success");
                    
                    header("Location: ../../pages/profile/dashboard.php");
                    exit;
                } else {
                    $error = "Failed to verify your account. Please try again.";
                    error_log("Database update failed: " . implode(", ", $stmt->errorInfo()));
                }
            } else {
                $error = "Invalid verification code. Please try again.";
                error_log("Invalid verification code for user_id: $user_id, code: $verification_code");
            }
        } catch (PDOException $e) {
            $error = "Database error occurred. Please try again.";
            error_log("PDO Error: " . $e->getMessage());
        }
    }
}

if(isset($_GET['resend']) && $_GET['resend'] == 'true') {
    try {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        $new_verification_code = generateVerificationCode();
        
        $stmt = $pdo->prepare("UPDATE users SET verification_code = ? WHERE id = ?");
        
        if($stmt->execute([$new_verification_code, $user_id])) {
            if(sendVerificationEmail($email, $user['name'], $new_verification_code)) {
                $resend_success = "Verification code has been resent to your email.";
                error_log("Verification code resent to: $email");
            } else {
                $resend_error = "Failed to send verification email. Please try again.";
                error_log("Failed to send verification email to: $email");
            }
        } else {
            $resend_error = "Failed to generate new verification code. Please try again.";
            error_log("Failed to update verification code for user_id: $user_id");
        }
    } catch (PDOException $e) {
        $resend_error = "Database error occurred. Please try again.";
        error_log("PDO Error during resend: " . $e->getMessage());
    }
}
?>

<div class="max-w-md mx-auto px-4 sm:px-6 bg-white p-6 sm:p-8 rounded-lg shadow-md mt-8 min-h-[63vh]">
    <h2 class="text-xl sm:text-2xl font-bold mb-4 sm:mb-6 text-center">Verify Your Email</h2>
    
    <p class="text-gray-600 mb-4 sm:mb-6 text-sm sm:text-base text-center">
        We've sent a verification code to <strong><?php echo $email; ?></strong>
    </p>
    
    <?php if(isset($error)): ?>
        <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if(isset($resend_success)): ?>
        <div class="bg-green-100 text-green-700 p-4 rounded mb-6">
            <?php echo $resend_success; ?>
        </div>
    <?php endif; ?>
    
    <?php if(isset($resend_error)): ?>
        <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
            <?php echo $resend_error; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" class="space-y-4 sm:space-y-6">
        <div class="mb-4 sm:mb-6">
            <label for="verification_code" class="block text-gray-700 font-medium mb-2">Verification Code</label>
            <input type="text" id="verification_code" name="verification_code" 
                   class="w-full px-3 py-2 sm:py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <button type="submit" class="w-full bg-blue-600 text-white py-2 sm:py-3 px-4 rounded-md hover:bg-blue-700">
            Verify Email
        </button>
    </form>
    
    <p class="mt-4 sm:mt-6 text-center text-gray-600 text-sm sm:text-base">
        Didn't receive the code? <a href="?resend=true" class="text-blue-600 hover:underline">Resend Code</a>
    </p>
</div>

<?php require_once '../../includes/footer.php'; ?>