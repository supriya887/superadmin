<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tizzy Dashboard</title>

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="crm.css">
    <link rel="stylesheet" href="responsive.css">

    <!-- Remix / Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>

    <!--========================
        Header
    =========================-->

    <header class="header">

        <div class="logo">
            <a href="index.php"><img src="images/logo.png" alt="Logo"></a>
        </div>


        <div class="right-main">

            <!-- Sidebar Toggle -->
            <button class="sidebar-toggle">
                <i data-lucide="panel-left-close"></i>
            </button>


            <!-- Search -->
            <div class="search-box">

                <input type="text" placeholder="Search">

                <button type="button">
                    <i data-lucide="search"></i>
                </button>

            </div>


            <!-- Header Right -->
            <div class="header-right">


                <!--========================
                    Notification
                =========================-->

                <!--========================
    Notification
=========================-->

                <div class="notification-wrapper">

                    <button
                        type="button"
                        class="icon-btn notification"
                        id="notificationBtn">

                        <i data-lucide="bell"></i>

                        <span class="notification-count">5</span>

                    </button>

                </div>


                <!--========================
    Notification Overlay
=========================-->

                <div
                    class="notification-overlay"
                    id="notificationOverlay">
                </div>


                <!--========================
    Notification Sidebar
=========================-->

                <aside
                    class="notification-sidebar"
                    id="notificationSidebar">

                    <!-- Header -->

                    <div class="notification-sidebar-header">

                        <div>
                            <h3>Notifications</h3>
                            <p>5 new notifications</p>
                        </div>

                        <button
                            type="button"
                            class="notification-close"
                            id="notificationClose">

                            <i data-lucide="x"></i>

                        </button>

                    </div>


                    <!-- Notification List -->

                    <div class="notification-sidebar-body">


                        <!-- Notification 1 -->

                        <a href="#" class="side-notification unread">

                            <div class="side-notification-content">

                                <div class="side-notification-top">

                                    <h4>New Support Ticket</h4>

                                    <span class="notification-dot"></span>

                                </div>

                                <p>
                                    A new support ticket has been created.
                                </p>

                                <small>
                                    5 minutes ago
                                </small>

                            </div>

                        </a>


                        <!-- Notification 2 -->

                        <a href="#" class="side-notification unread">

                            <div class="side-notification-content">

                                <div class="side-notification-top">

                                    <h4>Payment Received</h4>

                                    <span class="notification-dot"></span>

                                </div>

                                <p>
                                    Payment of ₹12,500 received successfully.
                                </p>

                                <small>
                                    20 minutes ago
                                </small>

                            </div>

                        </a>


                        <!-- Notification 3 -->

                        <a href="#" class="side-notification unread">

                            <div class="side-notification-content">

                                <div class="side-notification-top">

                                    <h4>Invoice Pending</h4>

                                    <span class="notification-dot"></span>

                                </div>

                                <p>
                                    Invoice #INV1025 is pending payment.
                                </p>

                                <small>
                                    1 hour ago
                                </small>

                            </div>

                        </a>


                        <!-- Notification 4 -->

                        <a href="#" class="side-notification">

                            <div class="side-notification-content">

                                <div class="side-notification-top">

                                    <h4>New Customer</h4>

                                </div>

                                <p>
                                    A new customer has been registered.
                                </p>

                                <small>
                                    2 hours ago
                                </small>

                            </div>

                        </a>


                        <!-- Notification 5 -->

                        <a href="#" class="side-notification">

                            <div class="side-notification-content">

                                <div class="side-notification-top">

                                    <h4>Ticket Escalated</h4>

                                </div>

                                <p>
                                    Support ticket SUP0011 has been escalated.
                                </p>

                                <small>
                                    3 hours ago
                                </small>

                            </div>

                        </a>


                    </div>


                    <!-- Footer -->

                    <div class="notification-sidebar-footer">

                        <button type="button" class="mark-read">
                            Mark all as read
                        </button>

                        <a href="notifications.php">
                            View All Notifications
                        </a>

                    </div>

                </aside>


                <!--========================
                    Profile
                =========================-->

                <div class="profile-wrapper">

                    <button
                        type="button"
                        class="profile"
                        id="profileBtn">

                        <div class="avatar">
                            J
                        </div>

                        <div class="profile-info">

                            <h5>Janak</h5>

                            <small>
                                Super Admin
                            </small>

                        </div>

                        <i
                            data-lucide="chevron-down"
                            class="profile-arrow">
                        </i>

                    </button>


                    <!-- Profile Dropdown -->

                    <div
                        class="profile-dropdown"
                        id="profileDropdown">

                        <div class="profile-card">
                            <div class="profile-pattern"></div>

                            <div class="profile-content">
                                <div class="profile-avatar">
                                    <span>J</span>
                                </div>

                                <h2>Janak Singh</h2>

                                <div class="profile-meta">
                                    <span>Partner ID : P123456</span>
                                    <span class="admin-badge">Admin</span>
                                </div>
                            </div>
                        </div>


                        <div class="profile-menu">

                            <a href="profile.php">

                                <i data-lucide="user"></i>

                                <span>
                                    My Profile
                                </span>

                            </a>


                            <a href="settings.php">

                                <i data-lucide="settings"></i>

                                <span>
                                    Settings
                                </span>

                            </a>


                            <div class="profile-divider"></div>


                            <a
                                href="logout.php"
                                class="logout">

                                <i data-lucide="log-out"></i>

                                <span>
                                    Logout
                                </span>

                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </header>
