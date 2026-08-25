<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Phone verification</h3>
                <p class="text-muted">For your security, verify the phone number on your WHMCS account before continuing.</p>

                {if $mfaError}
                    <div class="alert alert-danger" role="alert">{$mfaError|escape:'html'}</div>
                {/if}

                {if $mfaAvailable}
                    <div class="alert alert-info" role="alert">
                        Verification code will be sent to <strong>{$maskedPhone|escape:'html'}</strong>.
                    </div>

                    <div id="firebase-mfa-status" class="alert alert-secondary" role="status">
                        Click <strong>Send verification code</strong> to continue.
                    </div>

                    <div id="firebase-recaptcha-container" class="mb-3"></div>

                    <div class="mb-3">
                        <button type="button" id="firebase-send-code" class="btn btn-primary">Send verification code</button>
                    </div>

                    <div id="firebase-code-area" style="display:none;">
                        <div class="form-group mb-3">
                            <label for="firebase-otp">Verification code</label>
                            <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" id="firebase-otp" class="form-control" placeholder="6-digit code">
                        </div>
                        <button type="button" id="firebase-verify-code" class="btn btn-success">Verify and continue</button>
                    </div>

                    <form method="post" action="{$modulelink|escape:'html'}" id="firebase-token-form" style="display:none;">
                        <input type="hidden" name="mfa_csrf" value="{$mfaCsrf|escape:'html'}">
                        <input type="hidden" name="firebase_id_token" id="firebase-id-token" value="">
                    </form>

                    <script type="application/json" id="firebase-config">{$firebaseConfigJson nofilter}</script>
                    <script type="application/json" id="firebase-phone">"{$phoneE164|escape:'javascript'}"</script>

                    <script type="module">
                        import { initializeApp } from 'https://www.gstatic.com/firebasejs/12.17.1/firebase-app.js';
                        import { getAuth, RecaptchaVerifier, signInWithPhoneNumber, signOut } from 'https://www.gstatic.com/firebasejs/12.17.1/firebase-auth.js';

                        const statusEl = document.getElementById('firebase-mfa-status');
                        const sendButton = document.getElementById('firebase-send-code');
                        const verifyButton = document.getElementById('firebase-verify-code');
                        const codeArea = document.getElementById('firebase-code-area');
                        const otpInput = document.getElementById('firebase-otp');
                        const tokenInput = document.getElementById('firebase-id-token');
                        const tokenForm = document.getElementById('firebase-token-form');
                        const firebaseConfig = JSON.parse(document.getElementById('firebase-config').textContent);
                        const phoneNumber = JSON.parse(document.getElementById('firebase-phone').textContent);

                        const app = initializeApp(firebaseConfig);
                        const auth = getAuth(app);
                        auth.useDeviceLanguage();

                        let confirmationResult = null;
                        let verifier = null;

                        function setStatus(message, type = 'secondary') {
                            statusEl.className = 'alert alert-' + type;
                            statusEl.textContent = message;
                        }

                        function firebaseMessage(error) {
                            const code = error && error.code ? String(error.code) : '';
                            const messages = {
                                'auth/invalid-phone-number': 'The phone number saved on your account is invalid.',
                                'auth/too-many-requests': 'Too many OTP requests. Please wait before trying again.',
                                'auth/quota-exceeded': 'Firebase SMS quota has been exceeded. Please contact support.',
                                'auth/captcha-check-failed': 'reCAPTCHA verification failed. Please try again.',
                                'auth/code-expired': 'The verification code has expired. Request a new code.',
                                'auth/invalid-verification-code': 'The verification code is incorrect.',
                                'auth/network-request-failed': 'Network error while contacting Firebase. Please try again.'
                            };
                            return messages[code] || 'Firebase verification failed. Please try again.';
                        }

                        async function resetVerifier() {
                            if (verifier) {
                                try { verifier.clear(); } catch (e) {}
                            }
                            document.getElementById('firebase-recaptcha-container').innerHTML = '';
                            verifier = new RecaptchaVerifier(auth, 'firebase-recaptcha-container', {
                                size: 'normal',
                                'expired-callback': () => setStatus('reCAPTCHA expired. Please complete it again.', 'warning')
                            });
                            await verifier.render();
                        }

                        await resetVerifier();

                        sendButton.addEventListener('click', async () => {
                            sendButton.disabled = true;
                            setStatus('Sending verification code…', 'info');
                            try {
                                confirmationResult = await signInWithPhoneNumber(auth, phoneNumber, verifier);
                                codeArea.style.display = '';
                                otpInput.focus();
                                setStatus('Verification code sent. Enter the 6-digit SMS code.', 'success');
                                sendButton.textContent = 'Resend verification code';
                            } catch (error) {
                                setStatus(firebaseMessage(error), 'danger');
                                await resetVerifier();
                            } finally {
                                sendButton.disabled = false;
                            }
                        });

                        verifyButton.addEventListener('click', async () => {
                            const code = otpInput.value.replace(/\D/g, '');
                            if (!confirmationResult) {
                                setStatus('Request a verification code first.', 'warning');
                                return;
                            }
                            if (!/^\d{6}$/.test(code)) {
                                setStatus('Enter the 6-digit verification code.', 'warning');
                                return;
                            }

                            verifyButton.disabled = true;
                            setStatus('Verifying code…', 'info');
                            try {
                                const credential = await confirmationResult.confirm(code);
                                const idToken = await credential.user.getIdToken(true);
                                tokenInput.value = idToken;
                                try { await signOut(auth); } catch (e) {}
                                setStatus('Phone verified. Securing your WHMCS session…', 'success');
                                tokenForm.submit();
                            } catch (error) {
                                setStatus(firebaseMessage(error), 'danger');
                                verifyButton.disabled = false;
                            }
                        });
                    </script>
                {else}
                    <p>Please contact support to correct your WHMCS phone number or Firebase configuration.</p>
                {/if}
            </div>
        </div>
    </div>
</div>
