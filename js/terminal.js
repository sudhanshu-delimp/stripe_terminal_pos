var stripeTeminalTokenIs = '';
var Terminal= '';
var OrderNumber=0;
var ClientSecret = '';
var getTeminalToken = async function(){
    return await jQuery.ajax({
        method:"POST",
        url : terminal_obj.ajaxurl,
        dataType:'json',
        data : {action:'terminal_get_token'},
    });
}

getTeminalToken().then((token)=>{
    loadTerminal(token.secret);
});

async function loadTerminal(token){
    Terminal = await StripeTerminal.create({
        onFetchConnectionToken: async () => { return token },
        onUnexpectedReaderDisconnect: unexpectedDisconnect,
    });

    if (window.location.href.indexOf('/order-received/') > -1) {
        var orderNumber = window.location.href.split('order-received/');
        orderNumber = orderNumber[1].split('/?');
        orderNumber = orderNumber[0].replace('/','');
        OrderNumber = orderNumber;
        get_client_secret(OrderNumber).then((output)=>{
            if(output.status=='1'){
                ClientSecret = output.paymentIntentObj.client_secret;
                console.log("loadTerminal output: ",output);
                console.log("loadTerminal Token: ",token);
                console.log("loadTerminal ClientSecret: "+ClientSecret);
                if(jQuery(".woocommerce-order-details").length > 0){
                    jQuery(".woocommerce-order-details").append('<button id="DiscoverReader">Discover Reader</button>');
                    jQuery(".woocommerce-order-details").append('<table id="DiscoverReaderTable"><tr><td>Label</td><td>Sn.</td><td>Status</td><td>Action</td></tr><table>');
                }
                else{
                    jQuery("#main .woocommerce").append('<button id="DiscoverReader">Discover Reader</button>&nbsp;&nbsp;<button id="UseSimulator">Use Simulator</button>');
                    jQuery("#main .woocommerce").append('<table id="DiscoverReaderTable"><tr><td>Label</td><td>Sn.</td><td>Status</td><td>Action</td></tr><table>');
                }
                jQuery("body #DiscoverReader").trigger('click');
            }
            else{
                console.log("Other Paymnet Method");
            }

        });
    }
}

jQuery(document).on('click','#DiscoverReader',function(){
    jQuery("body #DiscoverReaderTable tr").remove();
    discoverReaders(Terminal).then((readers)=>{
        console.log("readers: ", readers);
        if(readers.length > 0){
            readers.map((reader)=>{
                var tr = jQuery("<tr></tr>");
                var td_label = jQuery(`<td>${reader.label}</td>`);
                tr.append(td_label);
                var td_serial_number = jQuery(`<td>${reader.serial_number}</td>`);
                tr.append(td_serial_number);
                var td_status = jQuery(`<td>${reader.status}</td>`);
                tr.append(td_status);
                var td_action = jQuery(`<td></td>`);
                var connectButton = jQuery('<button>Connect</button>');
                td_action.append(connectButton);
                connectButton.on('click',function(){
                    let selectedConnect  = jQuery(this);
                    selectedConnect.text('Please Wait...');
                    connectToReader(reader,Terminal).then((output)=>{
                        console.log(output);
                    }).catch((error)=>{
                        alert(error);
                        selectedConnect.text('Unable to connect');
                    });
                });
                tr.append(td_action);
                jQuery("body #DiscoverReaderTable").append(tr);
            });
        }
        else{
            jQuery("body #DiscoverReaderTable").append(`<tr><td colspan='4'>No reader found</td></tr>`);
        }
        console.log(readers);
    })
    .catch((error)=>{
        console.error(error);
    });
});

jQuery(document).on('click','#UseSimulator',function(){
    jQuery(this).text('Processing...');
    connectToSimulator(Terminal).then(()=>{
        capturePayment(Terminal,ClientSecret)
    });
});

const capturePayment = async(terminal,clientsecret)=>{
    terminal.collectPaymentMethod(clientsecret).then((payment_method)=>{
            console.log("payment_method: ",payment_method);
            terminal.processPayment(payment_method.paymentIntent).then((result)=>{
                jQuery('body #UseSimulator').text('Payment is processing...')
                console.log(result);
                if(!!result.error){
                    jQuery('body #UseSimulator').text('Use Simulator')
                }
                else{
                    var paymentIntentId = result.paymentIntent.id;
                    capture_paymentIntent(paymentIntentId).then((output)=>{
                        console.log("capture_paymentIntent",output);
                        if(output.status=='1'){
                            jQuery('body #UseSimulator').text('Payment is successfull').attr('disabled','disabled');
                        }
                        else{
                            jQuery('body #UseSimulator').text(output.message);
                        }
                    })
                }
            },(err)=>{
                console.log("err_43",err);
            });
            },(err)=>{
                console.log("err_46",err);
            });
}

const connectToSimulator = async (terminal) => {
        const simulatedResult = await terminal.discoverReaders({
            simulated: true,
        });
        console.log("simulatedResult: ",simulatedResult);
        await connectToReader(simulatedResult.discoveredReaders[0],terminal);
    };

    const connectToReader = async (selectedReader,terminal) => {
        console.log("connectToReader calling...",selectedReader);
        const connectResult = await terminal.connectReader(selectedReader);
        if (connectResult.error) {
            console.log("connectToReader err: ",connectResult.error);
            //alert(connectResult.error.message);
            throw connectResult.error.message;
        } else {
            if(selectedReader.id === 'SIMULATOR'){
                terminal.setSimulatorConfiguration({
                   testCardNumber: '4242424242424242',
                    //testCardNumber: '4000000000000002',
                });
            }
            return connectResult;
        }
    };

function unexpectedDisconnect() {
    console.log(unexpectedDisconnect);
}


var get_client_secret = async function(OrderNumber){
    return await jQuery.ajax({
        method:"POST",
        url : terminal_obj.ajaxurl,
        dataType:'json',
        data : {action:'get_client_secret',OrderNumber:OrderNumber},
    });
}

var capture_paymentIntent = async function(paymentIntentId){
    return await jQuery.ajax({
        method:"POST",
        url : terminal_obj.ajaxurl,
        dataType:'json',
        data : {action:'capture_paymentIntent',payment_intent_id:paymentIntentId},
    });
}

const discoverReaders = async (terminal) => {
    const discoverResult = await terminal.discoverReaders();
    if (discoverResult.error) {
        throw discoverResult.error;
    } else {
        return discoverResult.discoveredReaders;
    }
};