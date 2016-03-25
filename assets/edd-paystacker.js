jQuery('form#edd_purchase_form').on('submit', function(e){
  if(jQuery('input:hidden[name=edd-gateway]').val()=='paystack'){
    console.log("Using paystack");
    jQuery('form#edd_purchase_form').addClass("processing");
  
      cptd = parseInt(jQuery('#cptd').val());
      if (!cptd){
        payWithPaystack();
        e.preventDefault();
        //e.stopPropagation();
      }else{
        jQuery('form#edd_purchase_form edd-purchase-button').val("Complete Purchase");
      }
  }else{
    console.log("Not using Paystack. Carry on");
  };
});

function payWithPaystack(){
  var paystack = {};
  paystack['email'] = jQuery('form#edd_purchase_form #edd-email').val();

  if (isValidEmailAddress(paystack['email'])){
    jQuery('form#edd_purchase_form #cptd').val("0");
    jQuery('form#edd_purchase_form #txcode').val("");

    jQuery.each(jQuery('form#edd_purchase_form #new_paystack').data(), function(i, v) {
      paystack[i] = v;
    }); 

    var handler = PaystackPop.setup({
      key: paystack.key,
      email: paystack.email,
      amount: paystack.amount,
      ref: paystack.ref,
      callback: function(response){
        console.log(response);
        jQuery('form#edd_purchase_form #txcode').val(response.trxref);
        jQuery('form#edd_purchase_form #cptd').val("1");
        jQuery('form#edd_purchase_form').removeClass("processing");        
        jQuery('form#edd_purchase_form').submit();
        return true;
      },
      onClose: function(){
        jQuery.growl.warning({ message: "Window was closed.<br />Please retry the payment.", location:'tc'});
        jQuery('form#edd_purchase_form #edd-purchase-button').val("Complete Purchase");
        jQuery('form#edd_purchase_form span.edd-cart-ajax').remove();
      }
    });
    handler.openIframe();
  }else{
    jQuery.growl.error({ message: "A valid billing email address is compulsory.", location:'tc'});
    jQuery('form#edd_purchase_form #edd-email').focus().blur().focus();
  }
  return false;
}

function isValidEmailAddress(emailAddress) {
    var pattern = /^([a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+(\.[a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+)*|"((([ \t]*\r\n)?[ \t]+)?([\x01-\x08\x0b\x0c\x0e-\x1f\x7f\x21\x23-\x5b\x5d-\x7e\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|\\[\x01-\x09\x0b\x0c\x0d-\x7f\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))*(([ \t]*\r\n)?[ \t]+)?")@(([a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.)+([a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.?$/i;
    return pattern.test(emailAddress);
};