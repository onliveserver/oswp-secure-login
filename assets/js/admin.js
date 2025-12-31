/* OSWP Secure Login - Admin JavaScript */

jQuery(document).ready(function($) {
    
    // Update login slug preview dynamically
    $('#oswp_custom_login_slug').on('input', function() {
        var slug = $(this).val() || 'usroz2';
        $('#login-slug-preview').text(slug);
    });
    
    // Initialize slug preview
    var currentSlug = $('#oswp_custom_login_slug').val() || 'usroz2';
    $('#login-slug-preview').text(currentSlug);
    
    // Toggle form visibility based on enable/disable toggle
    function toggleFormVisibility() {
        // SMTP form
        var smtpEnabled = $('input[name="oswp_smtp_enabled"]').is(':checked');
        var $smtpCard = $('input[name="oswp_smtp_enabled"]').closest('.oswp-card');
        var $smtpFields = $smtpCard.find('.form-table tr').not(':first');
        var $smtpTest = $smtpCard.find('.oswp-smtp-test');
        
        if (smtpEnabled) {
            $smtpFields.show();
            $smtpTest.show();
            $smtpCard.removeClass('oswp-disabled');
        } else {
            $smtpFields.hide();
            $smtpTest.hide();
            $smtpCard.addClass('oswp-disabled');
        }
        
        // OTP form
        var otpEnabled = $('input[name="oswp_otp_enabled"]').is(':checked');
        var $otpCard = $('input[name="oswp_otp_enabled"]').closest('.oswp-card');
        var $otpFields = $otpCard.find('.form-table tr').not(':first');
        
        if (otpEnabled) {
            $otpFields.show();
            $otpCard.removeClass('oswp-disabled');
        } else {
            $otpFields.hide();
            $otpCard.addClass('oswp-disabled');
        }
        
        // Custom Login URL form
        var customLoginEnabled = $('input[name="oswp_custom_login_enabled"]').is(':checked');
        var $customLoginCard = $('input[name="oswp_custom_login_enabled"]').closest('.oswp-card');
        var $customLoginFields = $customLoginCard.find('.form-table tr').not(':first');
        
        if (customLoginEnabled) {
            $customLoginFields.show();
            $customLoginCard.removeClass('oswp-disabled');
        } else {
            $customLoginFields.hide();
            $customLoginCard.addClass('oswp-disabled');
        }
    }
    
    // Initial visibility check
    toggleFormVisibility();
    
    // Listen for toggle changes
    $('input[name="oswp_smtp_enabled"], input[name="oswp_otp_enabled"], input[name="oswp_custom_login_enabled"]').on('change', toggleFormVisibility);
    
    // Test SMTP
    $('#test_smtp').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $result = $('#smtp_result');
        var $debug = $('#smtp_debug_output');
        var email = $('#test_email').val();
        var showDebug = $('#smtp_debug').is(':checked');
        
        if (!email) {
            alert('Please enter a test email address');
            return;
        }
        
        $btn.prop('disabled', true).text('Sending...');
        $result.html('').removeClass('success error');
        $debug.hide().html('');
        
        $.post(oswpAdmin.ajaxUrl, {
            action: 'oswp_test_smtp',
            nonce: oswpAdmin.nonce,
            email: email,
            debug: showDebug
        }, function(response) {
            if (response.success) {
                $result.html('<strong>✓ Success:</strong> ' + response.data.message)
                    .removeClass('error').addClass('success');
                if (response.data.debug && response.data.debug.trim() !== '') {
                    $debug.text(response.data.debug).show();
                }
            } else {
                var errorMsg = response.data.message || 'Failed to send test email.';
                $result.html('<strong>✗ Error:</strong> ' + errorMsg)
                    .removeClass('success').addClass('error');
                if (response.data.debug && response.data.debug.trim() !== '') {
                    $debug.text('Debug Output:\n' + response.data.debug).show();
                }
            }
        }).fail(function(jqxhr, textStatus, errorThrown) {
            $result.html('<strong>✗ Error:</strong> Request failed. ' + textStatus + ': ' + errorThrown)
                .removeClass('success').addClass('error');
        }).always(function() {
            $btn.prop('disabled', false).text('Send Test Email');
        });
    });
    
    // Flush rewrite rules
    $('#flush_rewrite').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to flush rewrite rules?')) {
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('Flushing...');
        
        $.post(oswpAdmin.ajaxUrl, {
            action: 'oswp_flush_rewrite',
            nonce: oswpAdmin.nonce
        }, function(response) {
            alert(response.success ? 'Rewrite rules flushed successfully!' : 'Failed to flush rewrite rules.');
        }).always(function() {
            $btn.prop('disabled', false).text('Flush Rewrite Rules');
        });
    });
});
