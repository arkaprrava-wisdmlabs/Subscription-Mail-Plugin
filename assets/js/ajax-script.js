(function($){
    $(document).ready(function(){
        $('#subscription_form').submit(function(e){
            e.preventDefault();
            var link = js_config.ajax_url;
            var form = $('#subscription_form').serialize();
            var formData = new FormData;
            formData.append('action','subscribe');
            formData.append('subscribe', form);
            $.ajax({
                url: link,
                data: formData,
                processData: false,
                contentType: false,
                type: 'post',
                success: function(result){
                    alert(result['data']);
                },
                error: function(result){
                    let data = JSON.parse(result['responseText']);
                    alert(data['data']);
                }
            })
        })
    })
})(jQuery);