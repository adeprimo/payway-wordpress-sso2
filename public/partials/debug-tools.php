<p>Session:</p>
<pre>
<?php
    echo print_r($_SESSION, 1);
?>
</pre>
<script type="text/javascript">

    (function ($) {
        if (Tulo) {
            Tulo.register_event_listener('session_status', function (data) {
                if(data && data.active) {
                    $("#tulo_login").hide();
                    $("#tulo_logout").show();
                } else {
                    $("#tulo_login").show();
                    $("#tulo_logout").hide();
                }

                $('#tulo_session_status').html(JSON.stringify(data, null, 4));
                prettyPrint();
            });
            Tulo.register_event_listener('session_status_envelope', function (data) {
                $('#tulo_session_status_envelope').html(JSON.stringify(data, null, 4));
              prettyPrint();
            });

        }
        $(document).ready(function () {
            $("#tulo_login_url").html(Tulo.login_uri(window.location));
            $("#tulo_logout_url").html(Tulo.logout_uri(window.location));
            $('#tulo_update_status').click(function () {
                Tulo.session();
            });
            $('#tulo_logout').click(function () {
                Tulo.logout(window.location);
            });
            $('#tulo_login').click(function () {
                Tulo.login(window.location);
            });

        });
    })(jQuery);

</script>

<button id="tulo_update_status">Update status</button>
<button id="tulo_login" style="display: none;">Login</button>
<button id="tulo_logout" style="display: none;">Logout</button>

<h2>Session</h2>
<pre id="tulo_session_status"></pre>

<h2>Envelope</h2>
<pre id="tulo_session_status_envelope"></pre>

<h2>Login URL</h2>
<pre id="tulo_login_url"></pre>

<h2>Logout URL</h2>
<pre id="tulo_logout_url"></pre>