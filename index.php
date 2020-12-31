<!doctype html>
<html lang="cs">
<?php
session_start();
?>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta property="og:type" content="website">
    <meta property="og:title" content="RailWorks download station" />
    <meta property="og:description" content="Welcome to RailWorks download station!" />
    <meta property="og:url" content="https://dls.rw.jachyhm.cz" />
    <meta property="og:image" content="https://dls.rw.jachyhm.cz/android-chrome-512x512.png" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css"
        integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" 
      href="https://use.fontawesome.com/releases/v5.1.0/css/all.css" 
      integrity="sha384-lKuwvrZot6UHsBSfcMvOkWwlCMgc0TaWr+30HWe3a4ltaBwTZhyTEggF5tJv8tbt" 
      crossorigin="anonymous">
    <link rel="stylesheet" href="https://unpkg.com/bootstrap-table@1.18.0/dist/bootstrap-table.min.css">

    <title>RailWorks download station</title>

    <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
    <script src="https://unpkg.com/bootstrap-table@1.18.0/dist/bootstrap-table.min.js"></script>
    <script src="https://www.google.com/recaptcha/api.js?render=6LcDPNkZAAAAALoiQBUiOI5vWpSRZFWG1HciV3BT"></script>

    <script type="text/javascript">
        var loginErrorTimeout;
        var registrationErrorTimeout;
        var profileErrorTimeout;
        var pwdResetErrorTimeout;
        var infoTimeout;
        var errorTimeout;
        <?php 
        if (isset($_SESSION["logged"]) && $_SESSION["logged"]) {
            echo('var user = {logged:'.boolval($_SESSION["logged"]).', name:"'.$_SESSION["realname"].'", email:"'.$_SESSION["email"].'", id:'.$_SESSION["userid"].', privileges:'.$_SESSION["privileges"].'};');
        } else {
            echo('var user = {logged:false, name:"", email:"", id:null, privileges:null};');
        }
        ?>
        $(document).ready(function(){
            $(".login-form").submit(function(e) {
                e.preventDefault();

                grecaptcha.ready(function() {
                    grecaptcha.execute('6LcDPNkZAAAAALoiQBUiOI5vWpSRZFWG1HciV3BT', {action: 'login'}).then(function(token) {
                        var form = $(this);
                        var url = "api/login";

                        $.ajax({
                            type: "POST",
                            url: url,
                            data: {
                                email: $("#login-username").val(),
                                password:  $("#login-password").val(),
                                recaptcha_token: token,
                            },
                            success: function(data)
                            {
                                if (data.code < 0) {
                                    clearTimeout(loginErrorTimeout);
                                    $("#login-error").html(data.message).fadeIn();
                                    loginErrorTimeout = setTimeout(function(){$("#login-error").fadeOut();}, 5000);
                                    if (data.code == -2) {
                                        $("#resend-button").show();
                                    }
                                } else {
                                    user.logged = true;
                                    user.name = data.content.realname;
                                    user.email = data.content.email;
                                    user.id = data.content.userid;
                                    user.privileges = data.content.privileges;
                                    $("#login").modal('hide');
                                    $("#logged-button").html('Logged in: <b>'+data.content.realname+'</b>');
                                    $("#login-button").hide();
                                    $("#register-button").hide();
                                    $('#logged-button').show();
                                    if (user.privileges > 0) {
                                        $('#packages-button').show();
                                        $('#become-author-button').hide();
                                    } else {
                                        $('#packages-button').hide();
                                        $('#become-author-button').show();
                                    }
                                    if (user.privileges > 1) {
                                        $('#admin-button').show();
                                    } else {
                                        $('#admin-button').hide();
                                    }
                                    $('#profile-name').val(data.content.realname);
                                    $('#profile-email').val(data.content.email);
                                    $('#profile-password').val('');
                                }
                            }
                        });
                    });
                });
            });
            $(".register-form").submit(function(e) {
                e.preventDefault();

                grecaptcha.ready(function() {
                    grecaptcha.execute('6LcDPNkZAAAAALoiQBUiOI5vWpSRZFWG1HciV3BT', {action: 'register'}).then(function(token) {
                        var form = $(this);
                        var url = "api/register";

                        $.ajax({
                            type: "POST",
                            url: url,
                            data: {
                                nickname: $("#name").val(),
                                email: $("#mail").val(),
                                password:  $("#password").val(),
                                recaptcha_token: token,
                            },
                            success: function(data)
                            {
                                if (data.code < 0) {
                                    $("#registration-error").html(data.message).fadeIn();
                                    clearTimeout(registrationErrorTimeout);
                                    registrationErrorTimeout = setTimeout(function(){$("#registration-error").fadeOut();}, 5000);
                                } else {
                                    $("#register").modal('hide');
                                    $("#info").modal('show');
                                    $("#info-content").html("An activation email was sent to you.");
                                    clearTimeout(infoTimeout);
                                    infoTimeout = setTimeout(function(){$("#info").modal('hide');}, 3000);
                                }
                            }
                        });
                    });
                });
            });
            $(".profile-form").submit(function(e) {
                e.preventDefault();

                grecaptcha.ready(function() {
                    grecaptcha.execute('6LcDPNkZAAAAALoiQBUiOI5vWpSRZFWG1HciV3BT', {action: 'register'}).then(function(token) {
                        var form = $(this);
                        var url = "api/register?update";

                        $.ajax({
                            type: "POST",
                            url: url,
                            data: {
                                nickname: $("#profile-name").val(),
                                email: $("#profile-email").val(),
                                password:  $("#profile-password").val(),
                                recaptcha_token: token,
                            },
                            success: function(data)
                            {
                                if (data.code < 0) {
                                    $("#profile-error").html(data.message).fadeIn();
                                    clearTimeout(profileErrorTimeout);
                                    profileErrorTimeout = setTimeout(function(){$("#profile-error").fadeOut();}, 5000);
                                } else {
                                    $("#profile").modal('hide');
                                    $("#info").modal('show');
                                    $("#info-content").html(data.message);
                                    clearTimeout(infoTimeout);
                                    infoTimeout = setTimeout(function(){$("#info").modal('hide');}, 3000);
                                    $('#profile-name').val(data.newNick);
                                    $('#logged-button').html('Logged in: <b>'+data.newNick+'</b>');
                                }
                            }
                        });
                    });
                });
            });
            $(".become-author-form").submit(function(e) {
                e.preventDefault();
                if (user.logged && user.privileges == 0) {
                    grecaptcha.ready(function() {
                        grecaptcha.execute('6LcDPNkZAAAAALoiQBUiOI5vWpSRZFWG1HciV3BT', {action: 'claim_author'}).then(function(token) {
                            var form = document.forms.namedItem("become-author");
                            var url = "api/becomeAuthor";

                            var reqData = new FormData(form);
                            reqData.append("recaptcha_token", token);
                            reqData.append("userid", user.id);

                            var req = new XMLHttpRequest();
                            req.responseType = 'json';
                            req.onloadstart = function(){
                                $('#custom-file-progress-bar').css('width', '0%').attr('aria-valuenow', 0).html();
                                return true;
                            };

                            req.upload.onprogress = function(event) {
                                var percentComplete = (event.loaded/event.total)*100;
                                $('#custom-file-progress-bar').css('width', percentComplete+'%').attr('aria-valuenow', percentComplete).html(percentComplete.toFixed(2)+'%');
                            }
                                
                            req.onload = function(oEvent) {
                                if (req.status == 200) {
                                    var data = req.response;
                                    $('#custom-file-progress-bar').css('width', '0%').attr('aria-valuenow', 0).html();
                                    if (data.code < 0) {
                                        $("#error-content").html(data.message);
                                        $("#error").modal("show");
                                        clearTimeout(errorTimeout);
                                        errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
                                    } else {
                                        $("#become-author").modal("hide");
                                        $("#info-content").html(data.message);
                                        $("#info").modal("show");
                                        clearTimeout(infoTimeout);
                                        infoTimeout = setTimeout(function(){$("#info").modal("hide");}, 5000);
                                    }
                                } else {
                                    $("#error-content").html("Error "+req.status);
                                    $("#error").modal("show");
                                    clearTimeout(errorTimeout);
                                    errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
                                }
                            };

                            req.open("POST", url, true);
                            req.send(reqData);
                        });
                    });
                } else {
                    if (user.logged) {
                        $("#error-content").html("You already are author!");
                    } else {
                        $("#error-content").html("You need to be logged in if you want to send this form!");
                    }
                    $("#error").modal("show");
                    clearTimeout(errorTimeout);
                    errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
                }
            });
            $(".pwdReset-form").submit(function(e) {
                e.preventDefault();

                grecaptcha.ready(function() {
                    grecaptcha.execute('6LcDPNkZAAAAALoiQBUiOI5vWpSRZFWG1HciV3BT', {action: 'pwd_reset'}).then(function(token) {
                        var form = $(this);
                        var url = "api/pwd_reset";

                        $.ajax({
                            type: "POST",
                            url: url,
                            data: {
                                t: '<?php
                                if (isset($_SESSION["resetPwd"])) {
                                    echo($_SESSION["resetPwd"]);
                                }?>',
                                password:  $("#pwdReset-password").val(),
                                recaptcha_token: token,
                            },
                            success: function(data)
                            {
                                if (data.code < 0) {
                                    $("#pwdReset-error").html(data.message).fadeIn();
                                    clearTimeout(pwdResetErrorTimeout);
                                    pwdResetErrorTimeout = setTimeout(function(){$("#pwdReset-error").fadeOut();}, 5000);
                                } else {
                                    $("#pwdReset").modal('hide');
                                    $("#info").modal('show');
                                    $("#info-content").html(data.message);
                                    clearTimeout(infoTimeout);
                                    infoTimeout = setTimeout(function(){$("#info").modal('hide');}, 3000);
                                }
                            }
                        });
                    });
                });
            });
            $("#resend-button").hide();
            $('#login').on('hidden.bs.modal', function (e) {
                $("#resend-button").hide();
            })
            <?php
            if (isset($_SESSION["errorMessage"])) {
                echo('$("#error-content").html("'.$_SESSION["errorMessage"].'")
                $("#error").modal("show");
                clearTimeout(errorTimeout);
                errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);');
            } elseif (isset($_SESSION["successMessage"])) {
                echo('$("#info-content").html("'.$_SESSION["successMessage"].'")
                $("#info").modal("show");
                clearTimeout(infoTimeout);
                infoTimeout = setTimeout(function(){$("#info").modal("hide");}, 5000);');
            }
            $_SESSION["successMessage"] = null;
            $_SESSION["errorMessage"] = null;
            if (isset($_SESSION["logged"]) && $_SESSION["logged"]) {
                echo("$('#login-button').hide();$('#register-button').hide();$('#become-author-button').show();");
                echo("$('#profile-name').val('".$_SESSION["realname"]."');");
                echo("$('#profile-email').val('".$_SESSION["email"]."');");
                echo("$('#profile-password').val('');");
            } else {
                echo("$('#logged-button').hide();$('#packages-button').hide();$('#admin-button').hide();$('#become-author-button').hide();");
            }
            if (isset($_SESSION["privileges"]) && $_SESSION["privileges"] <= 1) {
                echo("$('#admin-button').hide();");
                if ($_SESSION["privileges"] <= 0) {
                    echo("$('#packages-button').hide();");
                }
            }
            if (isset($_SESSION["resetPwd"])) {
                echo('$("#pwdReset").modal("show");');
                $_SESSION["resetPwd"] = null;
            }
            ?>
            $("#show_hide_password .input-group-append").on('mousedown', function(event) {
                $('#show_hide_password input').attr('type', 'text');
                $('#show_hide_password i').removeClass( "fa-eye-slash" );
                $('#show_hide_password i').addClass( "fa-eye" );
            });
            $("#show_hide_password .input-group-append").on('mouseup', function(event) {
                $('#show_hide_password input').attr('type', 'password');
                $('#show_hide_password i').addClass( "fa-eye-slash" );
                $('#show_hide_password i').removeClass( "fa-eye" );
            });
        });
        function logOut() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'api/login', true);
            xhr.send();
            $('#logged-text').html();
            $('#login-button').show();
            $('#register-button').show();
            $('#become-author-button').hide();
            $('#logged-button').hide();
            $('#packages-button').hide();
            $('#admin-button').hide();
        }
        function resendEmail() {
            $.get('api/register?resend&email='+$("#login-username").val(), function(data) {
                if (data.code < 0) {
                    $("#error-content").html(data.message);
                    $("#error").modal("show");
                    clearTimeout(errorTimeout);
                    errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
                } else {
                    $("#info-content").html(data.message);
                    $("#info").modal("show");
                    clearTimeout(infoTimeout);
                    infoTimeout = setTimeout(function(){$("#info").modal("hide");}, 5000);
                }
            });
            $("#resend-button").hide();
            $("#login").modal('hide');
        }
        function resetPwd() {
            grecaptcha.ready(function() {
                grecaptcha.execute('6LcDPNkZAAAAALoiQBUiOI5vWpSRZFWG1HciV3BT', {action: 'register'}).then(function(token) {
                    if (validateEmail($("#login-username").val())) {
                        $.get('api/register?resetPwd&recaptcha_token='+token+'&email='+$("#login-username").val(), function(data) {
                            if (data.code >= 0) {
                                $("#info-content").html(data.message);
                                $("#info").modal("show");
                                clearTimeout(infoTimeout);
                                infoTimeout = setTimeout(function(){$("#info").modal("hide");}, 5000);
                            } else {
                                $("#error-content").html(data.message);
                                $("#error").modal("show");
                                clearTimeout(infoTimeout);
                                infoTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
                            }
                        });
                        $("#login").modal('hide');
                    } else {
                        clearTimeout(loginErrorTimeout);
                        if (isEmptyOrSpaces($('#login-username').val())) {
                            $("#login-error").html('Please enter valid email adress before reseting password.').fadeIn();
                            loginErrorTimeout = setTimeout(function(){$("#login-error").fadeOut();}, 5000);
                        } else {
                            $("#login-error").html($('#login-username').val()+' does not seem as valid email adress. Please enter one before reseting password.').fadeIn();
                            loginErrorTimeout = setTimeout(function(){$("#login-error").fadeOut();}, 5000);
                        }
                    }
                });
            });
        }
        function profile() {
            window.location.replace('?author='+user.id);
        }
        function isEmptyOrSpaces(str){
            return str === null || str.match(/^ *$/) !== null;
        }
        function validateEmail(email) {
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        }
    </script>
</head>

<body>
    <nav class="navbar navbar-expand-md navbar-dark">
        <a class="navbar-brand" href=".">
            <img src="favicon-32x32.png" width="30" height="30" alt="">
            Railworks download station
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto" style="margin-left: 40px">
                <li class="nav-item" id="login-button">
                    <a class="nav-link" href="#" data-toggle="modal" data-target="#login">Login</a>
                </li>
                <li class="nav-item" id="register-button">
                    <a class="nav-link" href="#" data-toggle="modal" data-target="#register">Register</a>
                </li>
                <li class="nav-item" id="become-author-button">
                    <a class="nav-link" href="#" data-toggle="modal" data-target="#become-author">Become an author</a>
                </li>
                <li class="nav-item" id="application-button">
                    <a class="nav-link" href="?application">Desktop application</a>
                </li>
                <li class="nav-item" id="packages-button">
                    <a class="nav-link" href="?manager">Upload package</a>
                </li>
                <li class="nav-item" id="admin-button">
                    <a class="nav-link" href="?admin">Administration</a>
                </li>
            </ul>
            <ul class="navbar-nav" style="margin-left: 40px">
                <li class="nav-item">
                    <div class="dropdown">
                        <button class="btn text-light dropdown-toggle" type="button" id="logged-button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <?php
                            if (isset($_SESSION["logged"]) && $_SESSION["logged"]) {
                                echo('Logged in: <b>'.$_SESSION["realname"].'</b>');
                            }
                            ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="logged-button">
                            <a class="dropdown-item" href="#" onclick="profile()">Profile</a>
                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#profile">Edit profile</a>
                            <a class="dropdown-item" href="#" onclick="logOut()">Log out</a>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </nav>
    <div class="container">
        <!-- Registrace -->
        <div class="modal fade" id="register" tabindex="-1" data-backdrop="static" aria-labelledby="registerTitle"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form class="register-form" autocomplete="off">
                        <div class="modal-header">
                            <h5 class="modal-title" id="registerTitle">Register</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="name">Nickname</label>
                                <input type="text" class="form-control" id="name" autocomplete="nickname" minlength="4" required>
                            </div>
                            <div class="form-group">
                                <label for="mail">Email address</label>
                                <input type="email" class="form-control" id="mail" aria-describedby="emailHelp" autocomplete="email" required>
                                <small id="emailHelp" class="form-text text-muted">We'll never share your email with
                                    anyone else.</small>
                            </div>

                            <div class="form-group">
                                <label for="password">Password</label>
                                <div class="input-group mb-2 mr-sm-2" id="show_hide_password">
                                    <input type="password" class="form-control" id="password" autocomplete="new-password" minlength="8" required>
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-eye-slash" aria-hidden="true"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="error" id="registration-error"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Register</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Přihlášení -->
        <div class="modal fade" id="login" tabindex="-1" aria-labelledby="loginTitle" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form class="login-form" autocomplete="off">
                        <div class="modal-header">
                            <h5 class="modal-title" id="loginTitle">Login</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="login-username">Email</label>
                                <input type="email" class="form-control" id="login-username" autocomplete="email" required>
                            </div>

                            <div class="form-group">
                                <label for="login-password">Password</label>
                                <div class="input-group mb-2 mr-sm-2" id="show_hide_password">
                                    <input type="password" class="form-control" id="login-password" autocomplete="current-password" minlength="8" required>
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-eye-slash" aria-hidden="true"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <a href="#" onclick="resetPwd()">Forgot password?</a>
                            </div>
                            <div class="error" id="login-error"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-warning" id="resend-button" onclick="resendEmail()">Resend verification email</button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Profile -->
        <div class="modal fade" id="profile" tabindex="-1" data-backdrop="static" aria-labelledby="profileTitle"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form class="profile-form" autocomplete="off">
                        <div class="modal-header">
                            <h5 class="modal-title" id="profileTitle">Edit profile</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="name">Name</label>
                                <input type="text" class="form-control" id="profile-name" autocomplete="nickname" minlength="4" required>
                            </div>
                            <div class="form-group">
                                <label for="mail">Email address</label>
                                <input type="email" readonly class="form-control-plaintext" id="profile-email" aria-describedby="emailHelp" autocomplete="email" required>
                                <small id="emailHelp" class="form-text text-muted">We'll never share your email with
                                    anyone else.</small>
                            </div>

                            <div class="form-group">
                                <label for="password">New password</label>
                                <div class="input-group mb-2 mr-sm-2" id="show_hide_password">
                                    <input type="password" class="form-control" id="profile-password" autocomplete="new-password" minlength="8" required>
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-eye-slash" aria-hidden="true"></i></div>
                                    </div>
                                </div>
                            </div>
                            Fill only items you want to change!
                            <div class="error" id="profile-error"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Become an author -->
        <div class="modal fade" id="become-author" tabindex="-1" data-backdrop="static" aria-labelledby="become-author-title"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form name="become-author" class="become-author-form" autocomplete="off">
                        <div class="modal-header">
                            <h5 class="modal-title" id="become-author-title">Become an RW DLS author</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="become-author-realname">Real name</label>
                                <input type="text" class="form-control" id="become-author-realname" autocomplete="name" minlength="4" maxlength="128" name="realname" required>
                                <small id="real-name-help" class="form-text text-muted">We'll never share your name with
                                    anyone else.</small>
                            </div>
                            <div class="form-group">
                                <label for="about-you">Short info about you</label>
                                <textarea class="form-control" id="become-author-description" rows="3" minlength="250" maxlength="2048" aria-describedby="become-author-description-help" name="about" placeholder="Slight info about you, your projects, why should you get permissions to upload packages, any proof of things you've already done for TS." required></textarea>
                                <small id="become-author-description-help" class="form-text text-muted">You can write this in either English, Deutsch, Polski, Česky, or Slovensky.</small>
                            </div>
                            <div class="form-group">
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="become-author-images" name="images[]" accept="image/*" multiple="multiple" />
                                    <label class="custom-file-label" for="become-author-images" id="imgname" required>Choose images of your work to upload</label>
                                </div>
                            </div>
                            <div class="progress">
                                <div id="custom-file-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%"></div>
                            </div>
                            <div class="error" id="become-author-error"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Submit request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Password reset -->
        <div class="modal fade" id="pwdReset" tabindex="-1" data-backdrop="static" aria-labelledby="pwdResetTitle"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form class="pwdReset-form" autocomplete="off">
                        <div class="modal-header">
                            <h5 class="modal-title" id="pwdResetTitle">Reset password</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="password">Password</label>
                                <div class="input-group mb-2 mr-sm-2" id="show_hide_password">
                                    <input type="email" hidden class="form-control-plaintext" aria-describedby="emailHelp" autocomplete="email">
                                    <input type="password" class="form-control" id="pwdReset-password" autocomplete="new-password" minlength="8" required>
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-eye-slash" aria-hidden="true"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="error" id="pwdReset-error"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Reset password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Error modal -->
        <div class="modal fade" id="error" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" id="error-dialog">
                <div class="modal-content" id="error-content"></div>
            </div>
        </div>

        <!-- Info modal -->
        <div class="modal fade" id="info" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" id="info-dialog">
                <div class="modal-content" id="info-content"></div>
            </div>
        </div>
        <?php
        if (isset($_GET["package"])) {
            $package_id = $_GET["package"];
            include "package.php";
        } else if (isset($_GET["author"])) {
            $author_id = $_GET["author"];
            include "author.php";
        } else if (isset($_GET["application"])) {
            include "application.php";
        } else if (isset($_GET["manager"])) {
            include "manager.php";
        } else if (isset($_GET["admin"])) {
            $author_id = $_GET["admin"];
            include "admin.php";
        } else {
            include "packages_list.php";
        }
        ?>
    </div>
    <footer>
    <p class="text-light" style="margin: auto">
        © Zdendaki.net &amp; JachyHm.cz 2020 | <a href="mailto:support@jachyhm.cz" class="text-light">support@jachyhm.cz</a>
    </p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx"
        crossorigin="anonymous"></script>
</body>

</html>