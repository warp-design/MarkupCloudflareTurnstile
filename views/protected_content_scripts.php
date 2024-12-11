<script>
    document.addEventListener('DOMContentLoaded', function() {
        const turnstileContainer = document.getElementById('turnstile-container');

        // Function to fetch and load the form HTML
        function loadForm(endpoint, filePath, targetContainer) {
            fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        filePath: filePath
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to load form');
                    }
                    return response.text();
                })
                .then(html => {
                    targetContainer.innerHTML = html; // Insert the form HTML into the container
                    targetContainer.style.display = 'block'; // Show the form container

                    // Find and execute any inline <script> tags
                    const scripts = targetContainer.querySelectorAll('script');
                    scripts.forEach(script => {
                        const newScript = document.createElement('script');
                        if (script.src) {
                            newScript.src = script.src;
                            newScript.async = script.async;
                        } else {
                            newScript.textContent = script.textContent;
                        }
                        document.head.appendChild(newScript);
                        document.head.removeChild(newScript); // Optional: cleanup
                    });

                    // Re-initialize form-specific JavaScript
                    if (typeof initializeFormScripts === 'function') {
                        initializeFormScripts(targetContainer);
                    }
                })
                .catch(error => {
                    console.error('Error loading form:', error);
                    console.error('Unable to load the form. Please try again later.');
                });
        }

        // Listen for Turnstile response
        window.turnstileCallback = function(token) {
            // Send the token to the server for validation
            fetch('/turnstile/verify', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        'cf-turnstile-response': token
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Hide Turnstile container
                        turnstileContainer.style.display = 'none';

                        // Find all elements with .turnstile_content class
                        const formContainers = document.querySelectorAll('.turnstile_content');
                        formContainers.forEach(container => {
                            const filePath = container.getAttribute('data-render-path'); // Get form file path from data attribute
                            if (filePath) {
                                loadForm('/turnstile/render', filePath, container);
                            }
                        });
                    } else {
                        console.error('Verification failed. Please try again.');
                    }
                })
                .catch(error => console.error('Error:', error));
        };

        // Set Turnstile callback
        const turnstile = document.querySelector('.cf-turnstile');
        turnstile.setAttribute('data-callback', 'turnstileCallback');
    });
</script>
