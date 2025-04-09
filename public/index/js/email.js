document.getElementById('contactForm').addEventListener('submit', function(event) {
    event.preventDefault();

    const button = document.getElementById('submit-button');
    const name     = document.getElementById('name').value;
    const email    = document.getElementById('email').value;
    const message  = document.getElementById('message').value;
    const language = document.getElementById('language').value;

    button.innerHTML = (language === 'en') 
    ? `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...`
    : `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...`;

    const formData = new FormData();
    formData.append('name', name);
    formData.append('destination', email);
    formData.append('message', message);
    formData.append('language', language);

    fetch('./../../app.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert();
        } else {
            console.error('Error: ' + data.error);
        }

        button.innerHTML = (language === 'en') ? 'Send message' : 'Enviar mensaje';
    }).catch(error => {
        button.innerHTML = (language === 'en') ? 'Send message' : 'Enviar mensaje';
        
        console.error('Error:', error);
        console.error('An error occurred while sending the message.');
    });
    document.getElementById('contactForm').reset(); 
});

function showAlert() {
    var alertMessage = document.getElementById('alertMessage');
    alertMessage.style.display = 'block'; 

    setTimeout(function() {
        alertMessage.style.display = 'none';
    }, 5000);
}