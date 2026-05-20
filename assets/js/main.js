;(function ($) {
    $(document).ready(function () {
        $('#aluc-save-btn').on('click', function(e){
            e.preventDefault();
            var $this = $(this);
            let slug = $('#aluc-new-login-url').val();
            $this.addClass('disable');
            $this.val('Save....');
            $.ajax({
                url: aluc_core.ajax_url,
                type: 'POST',
                data: {
                    action: 'aluc_save_slug',
                    slug: slug,
                    _nonce: aluc_core.nonce
                },
                success: function(res) {
                    if(res.success){
                        $('.aluc-success p').html(res.data.message);
                        $('.aluc-success').show();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                        
                    } else {
                        $('.aluc-error p').html(res.data.message);
                        $('.aluc-error').show();
                        $this.removeClass('disable');
                        $this.val('Submit');
                        setTimeout(() => {
                            $('.aluc-error').hide();
                        }, 2000);
                    }
                },
                error: function(){
                    alert("Something went wrong.");
                }
            });
    
        });
    });
})(jQuery);