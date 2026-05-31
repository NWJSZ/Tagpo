document.addEventListener('DOMContentLoaded', function () {

    const methodSelect = document.getElementById('method_select');
    const cardSection = document.getElementById('card_section');
    const gcashSection = document.getElementById('gcash_section');
    const paypalSection = document.getElementById('paypal_section');

    const cardNumber = document.getElementById('card_number');
    const expiry = document.getElementById('expiry');
    const cvv = document.getElementById('cvv');
    const gcashName = document.getElementById('gcash_name');
    const gcashNumber = document.getElementById('gcash_number');
    const paypalEmail = document.getElementById('paypal_email');
    const paypalNumber = document.getElementById('paypal_number');
    const phoneInput = document.getElementById('phone_input');

    function togglePaymentFields() {
        const method = methodSelect.value;

        // Hide all sections
        cardSection.style.display = 'none';
        gcashSection.style.display = 'none';
        paypalSection.style.display = 'none';

        // Reset all required attributes
        cardNumber.required = false;
        expiry.required = false;
        cvv.required = false;
        gcashName.required = false;
        gcashNumber.required = false;
        paypalEmail.required = false;
        paypalNumber.required = false;

        // Show and set required for selected method
        if (method === 'card') {
            cardSection.style.display = 'block';
            cardNumber.required = true;
            expiry.required = true;
            cvv.required = true;
        } else if (method === 'gcash') {
            gcashSection.style.display = 'block';
            gcashName.required = true;
            gcashNumber.required = true;
        } else if (method === 'paypal') {
            paypalSection.style.display = 'block';
            paypalEmail.required = true;
            paypalNumber.required = true;
        }
    }

    // init state
    togglePaymentFields();

    methodSelect.addEventListener('change', togglePaymentFields);

    // Backwards-compatible alias used in some templates
    function updatePaymentFields() { togglePaymentFields(); }

    /* =========================
       CARD NUMBER MASKING
    ========================= */
    cardNumber.addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');
        value = value.substring(0, 16);

        e.target.value = value.match(/.{1,4}/g)?.join(' ') || value;
    });

    /* =========================
       EXPIRY MASKING MM/YY
    ========================= */
    expiry.addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '').substring(0, 4);

        if (value.length >= 3) {
            e.target.value = value.substring(0, 2) + '/' + value.substring(2);
        } else {
            e.target.value = value;
        }
    });

    /* =========================
       CVV MASK (3 digits only)
    ========================= */
    cvv.addEventListener('input', function (e) {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 3);
    });

    /* =========================
       PHONE MASK (+63 format fix)
    ========================= */
    phoneInput.addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '').substring(0, 10);
        e.target.value = value;
    });

});