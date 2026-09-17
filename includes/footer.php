<?php $base_url = get_base_url(); ?>
<footer class="bg-dark text-light pt-5 pb-4 mt-auto border-top border-secondary border-opacity-25">
    <div class="container">
        <div class="row g-4 mb-4">
            <!-- Brand & Tagline -->
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px;">
                        <i class="bi bi-bag-check-fill fs-5"></i>
                    </div>
                    <span class="fs-4 fw-bold text-white">Eve<span class="text-warning">Need</span></span>
                </div>
                <p class="text-light opacity-75 small mb-3">
                    "Everything You Need, All in One Place." Experience seamless multi-category shopping with high-grade security, instant order tracking, and premier discounts.
                </p>
                <div class="d-flex gap-3">
                    <a href="#" class="text-light opacity-75 hover-warning fs-5"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-light opacity-75 hover-warning fs-5"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" class="text-light opacity-75 hover-warning fs-5"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="text-light opacity-75 hover-warning fs-5"><i class="bi bi-linkedin"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="text-white fw-bold mb-3 text-uppercase fs-7">Explore</h6>
                <ul class="list-unstyled footer-links small mb-0">
                    <li class="mb-2"><a href="<?= $base_url ?>index.php" class="text-light opacity-75 text-decoration-none hover-warning">Home</a></li>
                    <li class="mb-2"><a href="<?= $base_url ?>products.php" class="text-light opacity-75 text-decoration-none hover-warning">All Products</a></li>
                    <li class="mb-2"><a href="<?= $base_url ?>categories.php" class="text-light opacity-75 text-decoration-none hover-warning">Categories</a></li>
                    <li class="mb-2"><a href="<?= $base_url ?>wishlist.php" class="text-light opacity-75 text-decoration-none hover-warning">My Wishlist</a></li>
                    <li class="mb-2"><a href="<?= $base_url ?>cart.php" class="text-light opacity-75 text-decoration-none hover-warning">Shopping Cart</a></li>
                </ul>
            </div>

            <!-- Customer Service -->
            <div class="col-lg-3 col-md-6 col-6">
                <h6 class="text-white fw-bold mb-3 text-uppercase fs-7">Customer Service</h6>
                <ul class="list-unstyled footer-links small mb-0">
                    <li class="mb-2"><a href="<?= $base_url ?>orders.php" class="text-light opacity-75 text-decoration-none hover-warning">My Orders</a></li>
                    <li class="mb-2"><a href="<?= $base_url ?>track-order.php" class="text-light opacity-75 text-decoration-none hover-warning">Order Tracking</a></li>
                    <li class="mb-2"><a href="<?= $base_url ?>addresses.php" class="text-light opacity-75 text-decoration-none hover-warning">Saved Addresses</a></li>
                    <li class="mb-2"><a href="<?= $base_url ?>profile.php" class="text-light opacity-75 text-decoration-none hover-warning">Account Settings</a></li>
                    <li class="mb-2"><a href="<?= $base_url ?>admin-login.php" class="text-warning opacity-75 text-decoration-none hover-warning"><i class="bi bi-shield-lock me-1"></i>Admin Portal</a></li>
                </ul>
            </div>

            <!-- Contact & Newsletter -->
            <div class="col-lg-3 col-md-6">
                <h6 class="text-white fw-bold mb-3 text-uppercase fs-7">Stay Connected</h6>
                <p class="text-light opacity-75 small mb-2"><i class="bi bi-envelope me-2 text-warning"></i>support@eveneed.com</p>
                <p class="text-light opacity-75 small mb-3"><i class="bi bi-telephone me-2 text-warning"></i>+91 (800) 123-4567</p>
                <form onsubmit="event.preventDefault(); alert('Thank you for subscribing to EveNeed updates!');" class="input-group input-group-sm">
                    <input type="email" class="form-control" placeholder="Enter your email" required>
                    <button class="btn btn-warning fw-bold" type="submit">Join</button>
                </form>
            </div>
        </div>

        <hr class="border-secondary opacity-50 my-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center small text-light opacity-75">
            <div>
                &copy; <?= date('Y') ?> <b>EveNeed</b>. All rights reserved. Advanced Web Development Academic Project.
            </div>
            <div class="d-flex gap-3 mt-2 mt-md-0">
                <span><i class="bi bi-shield-check text-success me-1"></i>Simulated Secure SSL</span>
                <span><i class="bi bi-truck text-warning me-1"></i>Fast Delivery</span>
                <span><i class="bi bi-patch-check text-primary me-1"></i>100% Genuine</span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom EveNeed Scripts -->
<script src="<?= $base_url ?>assets/js/script.js"></script>
</body>
</html>