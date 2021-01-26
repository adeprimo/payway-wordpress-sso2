document.addEventListener("DOMContentLoaded", function() {

        var loginForms      = document.querySelectorAll('.js-tuloLogin'),
            logoutButtons   = document.querySelectorAll('.js-tuloLogout'),
            buyButtons      = document.querySelectorAll('.js-tuloBuy'),
            loggedIn        = document.cookie.indexOf('tpw_') !== -1;

        if (loginForms.length) {
            loginForms.forEach(function(loginForm) {
                if (loggedIn) {
                    loginForm.remove();
                } else {
                    loginForm.classList.remove('is-hidden');
                    loginForm.onsubmit = function(e) {
                        e.preventDefault();

                        var rememberCheckbox= loginForm.querySelector('input[type="checkbox"]');
                        var username        = loginForm.querySelector('input[type="text"]').value,
                            password        = loginForm.querySelector('input[type="password"]').value,
                            rememberMe      = rememberCheckbox ? rememberCheckbox.value : true,
                            submitButton    = loginForm.querySelector('input[type=submit]');

                        if (!username.length || !password.length) {
                            return false;
                        }

                        // Disable the submit button to prevent multiple requests
                        submitButton.disabled = true;

                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', tulo_params.url + '?action=tulo_login', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                var parsedResponse = JSON.parse(xhr.response);
                                if (parsedResponse.success) {
                                    // If there are no errors, reload the window to fetch the latest information
                                    if (window.localStorage) {
                                        localStorage.setItem('tulo_products', parsedResponse.products);
                                    }
                                    window.location.reload();

                                } else {
                                    if (parsedResponse.error_code == "invalid_credentials") {
                                        var message = parsedResponse.error.replace("$remaining_attempts", parsedResponse.remaining_attempts);
                                        generateErrorMessage(loginForm, message);
                                    } else if (parsedResponse.error_code == "account_frozen") {
                                        var date = new Date(parsedResponse.frozen_until);
                                        generateErrorMessage(loginForm, parsedResponse.error + ' ' + date.toLocaleString('SV-se'));
                                    } else {
                                        generateErrorMessage(loginForm, parsedResponse.error);
                                    }

                                    submitButton.disabled = false;
                                }
                            } else {
                                // Unknown error, output debug information to console
                                console.error(xhr.status, xhr.statusText, xhr.response);
                                submitButton.disabled = false;
                            }
                        };
                        xhr.send('username=' + encodeURIComponent(username) + '&password=' + encodeURIComponent(password) + '&persist=' + rememberMe);
                    }
                }
            });
        }

        if (logoutButtons.length) {
            logoutButtons.forEach(function(logoutButton) {
                if (!loggedIn) {
                    logoutButton.remove();
                } else {
                    logoutButton.classList.remove('is-hidden');
                    logoutButton.onclick = function(e) {
                        e.preventDefault();

                        logoutButton.disabled = true;

                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', tulo_params.url + '?action=tulo_logout', true);
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                if (window.localStorage) {
                                    localStorage.removeItem('tulo_products');
                                }
                                window.location.reload();
                            } else {
                                // Unknown error, output debug information to console
                                console.error(xhr.status, xhr.statusText, xhr.response);
                                logoutButton.disabled = false;
                            }
                        };
                        xhr.send();
                    }
                }
            });
        }

        if (buyButtons.length) {
            buyButtons.forEach(function(buyButton) {
                buyButton.onclick = function(e) {
                    e.preventDefault();

                    var product = this.dataset.product;

                    if (typeof product !== 'undefined') {
                        var url = '';

                        switch (tulo_settings.env)
                        {
                            case 'stage':
                                url += 'https://' + tulo_settings.oid + '.payway-portal.stage.adeprimo.se/v2/shop/' + product;
                                break;
                            case 'test':
                                url += 'https://' + tulo_settings.oid + '.payway-portal.test.adeprimo.se/v2/shop/' + product;
                                break;
                            default:
                                url += 'https://' + tulo_settings.oid + '.portal.worldoftulo.com/v2/shop/' + product;
                                break;
                        }

                        url += '?returnUrl=' + encodeURIComponent(decodeURIComponent(window.location));

                        window.location = url;
                    }

                    return false;
                }
            });
        }

        function generateErrorMessage( target, message ) {
            var currentError = target.querySelector('.js-tuloError');
            if (currentError) {
                currentError.remove();
            }

            var errorMessageNode = document.createElement('div');
            errorMessageNode.className = 'js-tuloError tuloErrorMessage';
            errorMessageNode.textContent = message;

            // Insert error message before the form's first child node
            target.insertBefore(errorMessageNode, target.childNodes[0]);
        }
});
