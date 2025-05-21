// Auto-scroll to bottom of chat and modal handling
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    const negotiationModal = document.getElementById('negotiationModal');
    if (negotiationModal) {
        negotiationModal.addEventListener('shown.bs.modal', function() {
            negotiationModal.querySelector('input[name="negotiation_price"]').focus();
        });
    }
    
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                form.submit();
            }
        });
    });
});