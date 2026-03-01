<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | FETCH</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/plugins/bootstrap/css/bootstrap.min.css') }}">
    <style>
        :root {
            --brand-primary: #F97316;
            --brand-dark: #1E293B;
        }
        * { font-family: 'Inter', sans-serif; }
        body {
            min-height: 100vh;
            display: flex;
            background: linear-gradient(135deg, #FFF7ED 0%, #FED7AA 100%);
        }
        .login-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, var(--brand-primary) 0%, #EA580C 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #fff;
            padding: 60px;
            position: relative;
            overflow: hidden;
        }
        .login-left::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            top: -100px;
            right: -100px;
        }
        .login-left h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 16px;
        }
        .login-left p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 400px;
            text-align: center;
        }
        .features-list {
            margin-top: 40px;
        }
        .features-list li {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
            font-size: 1rem;
        }
        .features-list i {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 0.875rem;
        }
        .login-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px;
            background: #fff;
        }
        .login-box {
            width: 100%;
            max-width: 420px;
        }
        .login-logo {
            font-size: 2rem;
            font-weight: 800;
            color: var(--brand-primary);
            text-align: center;
            margin-bottom: 40px;
        }
        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--brand-dark);
            margin-bottom: 8px;
        }
        .login-subtitle {
            color: #64748B;
            margin-bottom: 32px;
        }
        .form-group label {
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }
        .form-control {
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }
        .password-field {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748B;
            cursor: pointer;
        }
        .btn-login {
            background: var(--brand-primary);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 16px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            transition: all 0.2s;
        }
        .btn-login:hover {
            background: #EA580C;
            color: #fff;
        }
        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            color: #94A3B8;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #E2E8F0;
        }
        .divider span {
            padding: 0 16px;
            font-size: 0.875rem;
        }
        .social-login {
            display: flex;
            gap: 12px;
        }
        .social-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .social-btn:hover {
            border-color: var(--brand-primary);
        }
        .register-link {
            text-align: center;
            margin-top: 24px;
            color: #64748B;
        }
        .register-link a {
            color: var(--brand-primary);
            font-weight: 600;
            text-decoration: none;
        }
        .forgot-password {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 16px;
        }
        .forgot-password a {
            color: var(--brand-primary);
            font-size: 0.875rem;
            text-decoration: none;
        }
        @media (max-width: 768px) {
            .login-left { display: none; }
            .login-right { padding: 40px 24px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <h1><i class="fas fa-truck-moving mr-3"></i>FETCH</h1>
            <p>Your trusted partner for seamless deliveries and logistics solutions across Nigeria.</p>
            <ul class="features-list list-unstyled">
                <li><i class="fas fa-check"></i> Fast & Reliable Delivery</li>
                <li><i class="fas fa-check"></i> Real-time Tracking</li>
                <li><i class="fas fa-check"></i> Affordable Pricing</li>
                <li><i class="fas fa-check"></i> 24/7 Customer Support</li>
            </ul>
        </div>
        <div class="login-right">
            <div class="login-box">
                <div class="login-logo">
                    <i class="fas fa-truck-moving mr-2"></i>FETCH
                </div>
                <h2 class="login-title">Welcome Back!</h2>
                <p class="login-subtitle">Enter your credentials to access your account</p>

                @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <form action="{{ route('login') }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <div class="password-field">
                            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
                        </div>
                    </div>
                    <div class="forgot-password">
                        <a href="{{ route('password.request') }}">Forgot Password?</a>
                    </div>
                    <button type="submit" class="btn btn-login">Sign In</button>
                </form>

                <div class="divider"><span>OR</span></div>

                <div class="social-login">
                    <button class="social-btn">
                        <i class="fab fa-google text-danger mr-2"></i>Google
                    </button>
                    <button class="social-btn">
                        <i class="fab fa-facebook text-primary mr-2"></i>Facebook
                    </button>
                </div>

                <div class="register-link">
                    Don't have an account? <a href="{{ route('register') }}">Create Account</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(icon) {
            const input = icon.previousElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
