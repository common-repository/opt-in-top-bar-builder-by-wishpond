var WishpondPlugin = {
  timeout: null,

  actions: [
    'publish_campaign',
    'delete_campaign'
  ],

  messages: [
    'updated',
    'error'
  ],

  message: function(type, message) {
    if (WishpondPlugin.messages.indexOf(type) === -1) {
      console.log('This message type is not supported', type);
      return
    }

    if (WishpondPlugin.timeout) {
      clearTimeout(WishpondPlugin.timeout);
      WishpondPlugin.timeout = null;
      jQuery('#wishpond_message').remove();
    }

    jQuery('#wishpond_iframe').before('<div id="wishpond_message" class="' + type + '"><p>' + message + '</p></div>');

    WishpondPlugin.timeout = setTimeout(function(){
      jQuery('#wishpond_message').remove();
    }, 10000);
  },
  initialize: function() {
    var messageHandler = function(response){
      var action;

      if (response.data && response.data.action) {
        action = response.data.action;
      }

      if (WishpondPlugin.actions.indexOf(action) > -1) {
        WishpondPlugin.message('updated', 'Processing...');

        jQuery.ajax({
          type: 'post',
          url: JS.wordpress,
          dataType: 'json',
          data: {
            data: response.data,
            action: 'wishpond_ajax'
          }
        }).done(function(response) {
          if (response && response.message) {
            WishpondPlugin.message(response.message.type, response.message.text);
          }
        });
      } else {
        console.error('This action is not supported: ', action);
      }
    }

    XD.receiveMessage(messageHandler, JS.wishpondUrl);
    XD.receiveMessage(messageHandler, JS.wishpondSecureUrl);
  }
}

WishpondPlugin.initialize();
