<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Admin Panel - @yield('title', 'Tizzy Group')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        #wrapper { display: flex; width: 100%; align-items: stretch; }
        #sidebar { min-width: 250px; max-width: 250px; background: #343a40; color: #fff; transition: all 0.3s; min-height: 100vh; position: fixed; }
        #content { width: 100%; padding: 20px; margin-left: 250px; transition: all 0.3s; }
        .sidebar-header { padding: 20px; background: #2c3136; text-align: center; }
        #sidebar ul.components { padding: 20px 0; border-bottom: 1px solid #47748b; }
        #sidebar ul li a { padding: 12px 20px; font-size: 1.1em; display: block; color: #adb5bd; text-decoration: none; }
        #sidebar ul li a:hover { color: #fff; background: #495057; }
        #sidebar ul li.active > a { color: #fff; background: #0d6efd; }
        .navbar { background: #fff; border-bottom: 1px solid #dee2e6; margin-bottom: 20px; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
    </style>
</head>
<body>
    <div id="wrapper">
        @include('partner.partneradmin.partials.sidebar')
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-primary d-md-none">
                        <i class="fas fa-align-left"></i>
                    </button>
                    <form class="d-none d-md-flex ms-3" style="width: 300px;">
                        <div class="input-group">
                            <span class="input-group-text border-0 bg-transparent"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control border-0 shadow-none" placeholder="Search...">
                        </div>
                    </form>
                    <div class="ms-auto d-flex align-items-center">
                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown">
                                <img src="https://ui-avatars.com/api/?name=Admin" alt="admin" width="32" height="32" class="rounded-circle me-2">
                                <strong>Partner Admin</strong>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><a class="dropdown-item" href="#">Profile</a></li>
                                <li><a class="dropdown-item" href="#">Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#">Sign out</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                @yield('content')
            </div>

            @include('partner.partneradmin.partials.footer')
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   
    
    
    <script>
        //  formSubmit
        function formSubmit(url, redirect, id = "form") {
        $("#" + id).on("submit", function(e) {
            e.preventDefault();
            $.ajax({
                url: url,
                type: "POST",
                data: new FormData(this),
                contentType: false,
                cache: false,
                processData: false,
                dataType: "json",
                beforeSend: function(data) {
                    $("#spinner").show();
                    $("#submit").attr("disabled", true);
                },
                success: function(data) {
                    $("#spinner").hide();
                    $("#submit").attr("disabled", false);
                    if (data.error) {
                        $.each(data, function(key, value) {
                            if (value) {
                                $("#error-" + key).html(value);
                                $("#" + key).addClass("border-danger");
                            } else {
                                $("#error-" + key).html(" ");
                                $("#" + key).removeClass("border-danger");
                            }
                        });
                    }
                    if (data.success) {
                        $("#" + id + " .form-control").removeClass("border-danger");
                        $("#" + id + " .text-danger").html(" ");
                        alertify.success(data.success);
                        if (redirect != '') {
                            setTimeout(function() {
                                window.location.href = redirect;
                            }, 1000);
                        }
                    }
                },
                error: function(error) {
                    $("#spinner").hide();
                    $("#submit").attr("disabled", false);
                    errorMessage(error.status);
                }
            });
        });
    }
    </script>
    @yield('js')
</body>
</html>
