var stripeTeminalTokenIs = '';
var Terminal= '';
var getTeminalToken = async function(){
    return await jQuery.ajax({
        method:"POST",
        url : terminal_obj.ajaxurl,
        dataType:'json',
        data : {action:'terminal_get_token'},
    });
}
async function fetchConnectionToken() {
    // Your backend should call /v1/terminal/connection_tokens and return the JSON response from Stripe
    const response = await fetch(terminal_obj.ajaxurl,
        {
        method: "POST",
        body:JSON.stringify({action:'terminal_get_token'}),
        headers: {'Content-Type': 'application/json'}
        }
    );
    const data = await response.json();
    console.log("TeminalToken is2: "+data);
    return data.secret;
}

getTeminalToken().then((token)=>{
    console.log("token: ",token);
    loadTerminal(token.secret);
});

async function loadTerminal(token){
    Terminal = await StripeTerminal.create({
        onFetchConnectionToken: async () => { return token },
        onUnexpectedReaderDisconnect: unexpectedDisconnect,
    });
    //const result = await Terminal.discoverReaders();
    //const reader = result.discoverReaders[0];
    //await Terminal.connectReader(reader);
    // const item = {
    // description:'Hight',
    // amount: 2000,
    // quantity: 1
    // }

    // const cart = {
    // lineItem:[item],
    // currency: 'usd'
    // }
    // Terminal.setReaderDisplay({type:'cart',cart});
}



function unexpectedDisconnect() {
    console.log(unexpectedDisconnect);
}

loadTerminal();



//console.log(terminal);
// const result = await terminal.discoverReaders();
// const reader = result.discoverReaders[0];
// await terminal.connectReader(reader);

// const item = {
//     description:'Hight',
//     amount: 2000,
//     quantity: 1
// }

// const cart = {
//     lineItem:[item],
//     currency: 'usd'
// }
// terminal.setReaderDisplay({type:'cart',cart});

// $( document.body ).trigger( 'init_checkout' );
// $( document.body ).trigger( 'payment_method_selected' );
// $( document.body ).trigger( 'update_checkout' );
// $( document.body ).trigger( 'updated_checkout' );
// $( document.body ).trigger( 'checkout_error' );
// $( document.body ).trigger( 'applied_coupon_in_checkout' );
// $( document.body ).trigger( 'removed_coupon_in_checkout' );
jQuery(document).ready(function($) {
    // Add an event listener to the "Place Order" button
    $('form.checkout').on('checkout_place_order', async function() {
        // Your custom code here

        // For example, you can show an alert on success
        
        var formData = $(this).serialize();
        var paymentIntent = await jQuery.ajax({
            method:"POST",
            url : terminal_obj.ajaxurl,
            dataType:'json',
            data : {action:'stripe_payment_intent',form_data:formData},
        });
        console.log("paymentIntent2: ",paymentIntent);
        alert('Order placed successfully!');
    });
});

// Terminal.collectPaymentMethod('client_secret').then((payment_method)=>{
//     Terminal.processPayment(payment_method.paymentIntent).then((result)=>{
//         console.log(result.paymentIntent.id);
//     },(err)=>{

//     });
// },(err)=>{

// });