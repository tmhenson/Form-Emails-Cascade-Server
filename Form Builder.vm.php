#set ( $callingPageData = $_XPathTool.selectSingleNode($contentRoot, "/system-index-block/calling-page/system-page") )
#set ( $formConfigContent = $_XPathTool.selectSingleNode($callingPageData, "system-data-structure/resultsFile/form-config/content/system-data-structure"))
#set ( $siteName = $callingPageData.getChild("system-data-structure").getChild("resultsFile").getChild("saveInSite").text)
#set ( $waysToSave = $_XPathTool.selectNodes($callingPageData, "system-data-structure/results/value"))
#set ( $dataDefType = $_XPathTool.selectSingleNode($callingPageData, "system-data-structure/resultsFile/dataDefType").value)


#set ( $emailSend = "")
#set ( $excelSave = "")
#set ( $cmsSave = "")#foreach ($wayToSave in $waysToSave)
    #if ($wayToSave.text == "send-email")
        #set ( $emailSend = "send-email")
    #elseif ($wayToSave.text == "save-to-excel")
        #set ( $excelSave = "save-to-excel")
    #elseif ($wayToSave.text == "save-to-cms")
        #set ( $cmsSave = "save-to-cms")
    #end
#end
[system-view:external]
    <?php
    error_reporting(E_ALL);
        if (sizeof($_POST) > 0){ //POSTED FORM, PROCESS IT
            //path to your recaptchalib.php file on your production site
            require_once('path/recaptchalib.php');
            //check the response
        $resp = recaptcha_check_answer("YOUR PRIVATE KEY HERE", $_SERVER["REMOTE_ADDR"], $_POST["recaptcha_challenge_field"], $_POST["recaptcha_response_field"]);
          //if the response is correct
          if ( $resp->is_valid ){
            $message = "";
            $subject = "${callingPageData.getChild('system-data-structure').getChild('ifEmailing').getChild('emailHeading').text}";
            $from_address = "${callingPageData.getChild('system-data-structure').getChild('ifEmailing').getChild('fromAddress').text}";
            $xmlContentInt = "";
            $sendEmail = ("${emailSend}" != "") ? true : false;
            $excelFile = ("${excelSave}" != "") ? true : false;
            $cmsFile = ("${cmsSave}" != "") ? true : false;
            $toArray = array();
            $required_fields = array(); // Holds the field names of fields that are required
            $form_fields = array(); // Holds the field names of all fields provided in the submitted data
            $form_values = array(); // Holds the values of all fields provided in the submitted data
            $form_submitted = true; // indicates if the form can be submitted or if it had data entry errors    
            function getParentFolderOutOfPath($path){
                return substr($path, 0, strripos($path,'/'));
            }           
            // Go through each post request and add the information to the message and to the content of the xml file
            foreach($_POST as $key => $value) {
                if ((substr($key, -1 * strlen("_REQUIRED")) == "_REQUIRED") and ($value == "T"))
                { 
                    // If a required field is found within the submit data
                    ${_EscapeTool.d}required_fields[substr($key, 0, strlen($key) - strlen("_REQUIRED"))] = true;
                }
                elseif(($key != "send-email")and($key != "save-to-cms") and ($key != "save-to-excel") and ($key != "form-config") and ($key != "emails"))
                { 
                    if (is_array($value)){
                        // If this is a field that actually provides the script with submitted content
                        ${_EscapeTool.d}form_fields[] = "$key";
                        ${_EscapeTool.d}form_values[] = implode(", ", $value);
                        $message .= "$key: ".implode(", ", $value)."\r\n";
                    }else{
                        // If this is a field that actually provides the script with submitted content
                        ${_EscapeTool.d}form_fields[] = "$key";
                        ${_EscapeTool.d}form_values[] = "$value";
                        $message .= "$key: $value\r\n";
                    }
                }
            }
            // Check that all required fields were adequately provided
                $unsatisfied_fields = array(); // A list of names of the required fields that have no submitted data
                foreach($form_fields as $field)
                {
                    if(isset($required_fields["$field"]) and $_POST["$field"] == "")
                    { 
                            // If one of the form fields are required and has no data provided
                            ${_EscapeTool.d}unsatisfied_fields[] = "$field"; // Add the field name to the list of unsatisfied required fields
                    }
                }
                if(!empty($unsatisfied_fields))
                { 
                    // If not all the required fields had submitted data provided
                    echo "<b>Error: The following required fields were not provided:</b><br /> <ul>";
                        // Notify the user of the fields that were required but contained no submitted data
                    foreach($unsatisfied_fields as $field)
                        {
                                echo "<li>$field</li>";
                        }
                   // echo "</ul><br /><b>The form's data was not sent. Please hit the 'Back' button, fill in the required fields mentioned above, and resubmit the form.</b><br />";
                    echo "</ul><br /> 
                        <link href='http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' rel='stylesheet' type='text/css' />
                        <div class='ui-widget'>
                        <div class='ui-state-error ui-corner-all' style='padding: 0 .7em;'>
                            <p><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'></span>The form's data was not sent. Please hit the 'Back' button, fill in the required fields mentioned above, and resubmit the form.</p></div></div><br />";
                    // indicate that the form had data entry errors
                    $form_submitted = false;
                }
            if ($form_submitted) {  
            // If the send email option was chosen, send the email
            if($sendEmail)
            {
                #set ( $emailAddresses = $_XPathTool.selectNodes($callingPageData, "system-data-structure/ifEmailing/email-address"))
                #foreach ($emailAddress in $emailAddresses)
                    ${_EscapeTool.d}toArray[] = "${emailAddress.text}";   
                #end
                $to = ""; // Create the comma-delimited list of receiving email addresses that are having this form's submitted data emailed to
                for($i = 0; $i < count($toArray) - 1; $i++)
                {
                    $to .= $toArray[$i].", ";
                }
                $to .= ${_EscapeTool.d}toArray[count($toArray) - 1];
                $headers = "From: $from_address\r\n";
                
                    if (!mail($to,$subject,$message,$headers))
                    { 
                            // Send the email, and if the email was not successfully sent
                            echo "Error: Email could not be sent...<br />";
                            // Notify the user that the email containing the user's form data was not sent
                    }
                
            }
            // If CMS file or Excel file option was chosen, get CMS information
            if($cmsFile || $excelFile) {
                $cascade_address = "${formConfigContent.getChild('cascadeAddress').text}";
                $username = "${formConfigContent.getChild('username').text}";
                $password = "${formConfigContent.getChild('password').text}";
                $fname = "${callingPageData.getChild('system-data-structure').getChild('resultsFile').getChild('xmlFileName').text}";
                $client = new SoapClient("https://cms-local.tamu.edu/ws/services/AssetOperationService?wsdl", array('trace' => 1));
             }          
            // If CMS File option was chosen, upload to CMS
            if($cmsFile) {
                  $contentTypePath = "${formConfigContent.getChild('contentTypePath').text}";
                  $path = "${callingPageData.getChild('system-data-structure').getChild('resultsFile').getChild('formPath').getChild('path').text}";        
                  if(!is_null($path) and ($path != "/")){
                        $folder = getParentFolderOutOfPath($path);
                        try 
                        {   
                            $page = array();
                            $page['name'] = $fname;
                            $page['parentFolderPath'] = $folder;
                            $page['siteName'] = "";                            
                            $page['contentTypePath'] = $contentTypePath;              
                            $page['structuredData']['structuredDataNodes']['structuredDataNode'] = array();
                            foreach($_POST as $key => $value) 
                            {
                                if (($key != "send-email")and($key != "save-to-cms") and ($key != "save-to-excel") and (substr($key, -1 * strlen("_REQUIRED")) != "_REQUIRED") and ($key != "emails") and ($key != "form-config"))
                                {
                                    #if($dataDefType != "Generic Output")
                                        // use this for custom data definition output
                                           ${_EscapeTool.d}page['structuredData']['structuredDataNodes']['structuredDataNode'][] = array(
                                            'identifier' => $key,
                                            'type' => 'text',
                                            'text' => $value
                                        );

                                    #else
                                        // use this for generic data definition output:
                                        // <system-data-structure>
                                        //    <group identifier="field" multiple="true">
                                        //        <text identifier="id"/>
                                        //        <text identifier="value"/>
                                        //    </group>
                                        // </system-data-structure>
                                            ${_EscapeTool.d}page['structuredData']['structuredDataNodes']['structuredDataNode'][] = array(
                                                'identifier' => 'field',
                                                'type' => 'group',
                                                'structuredDataNodes' => array(                                                                                    
                                                    'structuredDataNode' => array(
                                                        array(
                                                            'identifier' => 'id',
                                                            'text' => $key,
                                                            'type' => 'text'),
                                                        array(                                                                                            
                                                            'identifier' => 'value',
                                                            'text' => $value,
                                                            'type' => 'text')                                                                                        
                                                    )
                                                )
                                            );
                                    #end
                                }
                            }
                            $create_params = array (
                                'authentication' => array( 
                                    'password' => $password,    
                                    'username' => $username
                                ),        
                                'asset' => array(
                                    'page' => $page
                                    // Uncomment this section if a workflow should be started on creation of a page in Cascade using the form.  
                                    // WorkflowName can be whatever the user wants.  
                                    // WorkflowDefinitionPath should be the Workflow to be started.  
                                    // WorkflowComments may be modified as well.
                                    /*,
                                    'workflowConfiguration' => array( 
                                            'workflowName' => 'New Item',    
                                            'workflowDefinitionPath' => 'Create-Edit Workflow',
                                            'workflowComments' => 'Asset created by outside user.'    
                                    )*/
                                 )
                            );
                            try 
                            {
                                $client->create($create_params);
                            } 
                            catch (Exception $e) 
                            {
                                print_r($client->__getLastResponse());
                                print ($e->getMessage());
                                print("Error: File creation request failed to conform to the WSDL.<br />");
                            }
                        } 
                        catch (Exception $e) 
                        {
                                print_r($client->__getLastResponse());
                                print("Error: File creation request failed to conform to the WSDL.<br />");
                        }
                  }
            }
            // If Excel option was chosen, upload Excel file to CMS
            if($excelFile) 
            {               
                    $path = "${callingPageData.getChild('system-data-structure').getChild('resultsFile').getChild('formPath').getChild('path').text}";
                    if(($path != "") and ($path != "/"))
                    {
                        $folder = getParentFolderOutOfPath($path);
                        $firstRow = array();
                        $thisRow = array();
                        foreach($_POST as $key => $value) 
                        {
                            if (($key != "send-email")and($key != "save-to-cms")and($key != "save-to-excel")and(substr($key, -1 * strlen("_REQUIRED")) != "_REQUIRED")and($key != "emails")and($key != "form-config"))
                            {
                                $firstRow[$key] = $key;    
                                if (is_array($value)){
                                    $thisRow[$key] = implode(", ", $value);
                                }else{
                                    $thisRow[$key] = $value;
                                }
                            }
                        }
        
                        $doc = array($firstRow, $thisRow);
                        // generate excel file
                        $xls = new Excel_XML;
                        $xls->addArray ( $doc );
                        $create_params = array (
                                'authentication' => array( 
                                    'password' => $password,    
                                    'username' => $username
                                ),
                                'identifier' => array(    
                                    'path' => array(
                                        'path' => $folder . "/".$fname.".xml",
                                        'siteName' => '${siteName}'
                                    ), 
                                    'type' => "file"
                                )
                        );
                        $client->read($create_params);
                        $readResult=$client->__getLastResponse();    
                        $exists_in_cms = substr($readResult, strpos($readResult, "<success>")+9, 4)=="true";                                        
                        try 
                        {
                            if ($exists_in_cms)
                            {
                                $client->read($create_params);
                                $res = $client->__getLastResponse();
                                $currentContent = substr($res,strripos($res, "<text>")+6,strripos($res,"</text>")-(strripos($res, "<text>")+6));
                                $currentContent = fixLTGT($currentContent);
                                $newRowPosition = strpos($currentContent, "</Table>");
                                $doc = array($thisRow);
                                $xls = new Excel_XML;
                                $xls->addArray ( $doc );
                                $docWithThisRow = $xls->generateXML();
                                $thisRowStartIndex = strpos($docWithThisRow, "<Row>");
                                $thisRowEndIndex = strpos($docWithThisRow, "</Row>")+6;
                                $thisRowLength = $thisRowEndIndex-$thisRowStartIndex;
                                $thisRow = substr($docWithThisRow, $thisRowStartIndex, $thisRowLength);
                                $thisRow .= "\r\n";
                                
                                $newContent = substr($currentContent, 0, $newRowPosition);
                                $newContent .= $thisRow;
                                $newContent .= substr($currentContent, $newRowPosition);
                                $newContent = fixLTGT($newContent);
                        
                                $create_params = array (
                                    'authentication' => array( 
                                        'password' => $password,    
                                        'username' => $username
                                    ),  
                                    'asset' => array(
                                        'file' => array(
                                            'name' => $fname.".xml",
                                            'parentFolderPath' => $folder, 
                                            'metadataSetPath' => "/Default",
                                            'path' => $folder . "/".$fname.".xml",
                                            'siteName' => '${siteName}',
                                            'text' => $newContent
                                        )
                                    )
                                );
                                
                                try 
                                {
                                        $response = $client->edit($create_params);
                                } 
                                catch (Exception $e) 
                                {
                                    print("Error: File edition request failed to conform to the WSDL.<br />");
                                }
                            }
                            else 
                            {
                                try 
                                {                                                           
                                    $create_params = array (
                                        'authentication' => array( 
                                                'password' => $password,    
                                                'username' => $username
                                        ),        
                                        'asset' => array(
                                                'file' => array(
                                                        'name' => $fname.".xml",
                                                        'parentFolderPath' => $folder, 
                                                        'siteName' => '${siteName}',
                                                        'metadataSetPath' => "/Default",
                                                        'text' => $xls->generateXML()
                                                )
                                        )
                                    );                       
                                    try 
                                    {
                                        $response = $client->create($create_params);
                                    } 
                                    catch (Exception $e) 
                                    {
                                        print("Error: File creation request failed to conform to the WSDL.<br />");
                                    }
                                } 
                                catch (Exception $e) 
                                {
                                    print("Error: File creation request failed to conform to the WSDL.<br />");
                                }
                            }
                        }
                        catch (Exception $e) 
                        {
                                print("Error: finding folder in CMS.<br />");
                        }
                    }
            }
        ?>  
                <!--#START-CODE
                <script type="text/javascript">
                    window.location.href = window.location.href.replace('false', 'true');
                </script>
                #END-CODE-->
        <?php
      
        }
        }else{
         ?><div class="error">Problem with the SPAM checker please go back and retry!</div><?php
        }}
        elseif ($_GET['submitted'] == 'true') {  //NO POST, FORM submitted SHOW confirmation
                    ?>${_SerializerTool.serialize($callingPageData.getChild('system-data-structure').getChild('confirmation_text'), true)}<?php
        }
        
    else{ 
    ?>
[/system-view:external]


#set ( $callingPageStructure = $_XPathTool.selectSingleNode($callingPageData, "system-data-structure") )
#set ( $opening = $callingPageStructure.getChild("opening") )

<div class="full">
    <p>$_SerializerTool.serialize($opening,true)</p>
</div>
<div class="full">
<form action="?submitted=true" method="POST" id="webForm">
    
    
        #set ( $formItems = $_XPathTool.selectNodes($callingPageStructure, "form_item") )
        #foreach ($formItem in $formItems)
            #set ($outputOriginal = $formItem.getChild("name").text)
            #set ($output = $formItem.getChild("name").text.replace(" ", "_"))
            #set ($output2 = $output.replace("/", "_"))
            #set ($name = $output2)
            #set ($formItemType = $formItem.getChild("type").text)
            #if ($formItemType =='hidden')
                <input name="${name}" type="hidden" value="${formItemType}"/>
            #else
                #set ($outtitle = $formItem.getChild("title").text)
                #if ($outtitle != '')
                <h3><label>${outtitle}:</label></h3>
                #end
           
                        <label for="${name}">
                            ${outputOriginal} 
                            #set ($formItemRequired = 'No')
                            #set ($formItemRequired = $formItem.getChild("required").getChild("value").text)
                            #if ($formItemRequired == 'Yes')
                                <font color="red">*</font>
                            #end
                        </label><br/>
                  
                            #set ($formItemRequired = 'No')
                            #set ($formItemRequired = $formItem.getChild("required").getChild("value").text)
                            #set ($formItemDefaultValue = $formItem.getChild("default_value").text)
                            #if ($formItemRequired == 'Yes')
                                <input name="${name}_REQUIRED" type="hidden" value="T"/>
                            #end
                            #set ( $formItemType = $formItem.getChild("type").text)
                            #if ($formItemType =='dropdown')
                                <select name="${name}" size="1">
                                    #if ($formItemDefaultValue == 'Yes' )
                                        <option SELECTED="true" value="${formItemDefaultValue}">$formItemDefaultValue</option>
                                        $formItemDefaultValue
                                    #end
                                    #set ( $defaultValue = $_XPathTool.selectSingleNode($formItem, "default_value/text()") )
                                    #set ( $valuesWithinForm = $_XPathTool.selectNodes($formItem, "value[string(text()) != string(default_value/text())]") )
                                    #foreach ($valueWithinForm in $valuesWithinForm)
                                        <option value="${valueWithinForm.text}">$valueWithinForm.text</option>
                                    #end
                                </select>
                            #elseif (($formItemType =='checkbox') || ($formItemType =='radio'))
                                #if ($formItemDefaultValue == 'Yes' )
                                <input CHECKED="true" id="${name}" name="${name}" type="${formItemType}" label=${outputOriginal}>
                                    $formItem.getChild("default_value").text
                                </input><br/><br/>
                                #end
                                #set ( $valuesWithinForm2 = $_XPathTool.selectNodes($formItem, "value[string(text()) != string(default_value/text())]") )
                                #foreach ($valueWithinForm2 in $valuesWithinForm2)
                                    <input id="${name}" name="${name}" type="${formItemType}" value="${valueWithinForm2.text}">$valueWithinForm2.text  </input><br/>
                                #end
                            #elseif ($formItemType =='textarea')
                                #if ($formItemRequired == 'Yes')
                                    <textarea cols="25" name="${name}" class="required" rows="5"></textarea>
                                #else
                                    <textarea cols="25" name="${name}" rows="5"></textarea>
                                #end
                            #elseif ($formItemType =='states')
                                <?php require_once '../config/functions.php'; 
                                        getStates(); ?>
                            #elseif ($formItemType == 'date')
                                <input id="${name}" name="${name}" label="${outputOriginal}" class="datepicker" type="text" value="${formItem.getChild("default_value").text}"/>
                            #elseif ($formItemType == 'email')    
                                <input id="${name}" name="${name}" label="${outputOriginal}" class="required email" type="text" value="${formItem.getChild("default_value").text}"/>
                            #else
                                #if ($formItemRequired == 'Yes')
                                    <input id="${name}" name="${name}" label="${outputOriginal}" class="required" type="${formItemType}" value="${formItem.getChild("default_value").text}"/>
                                #else
                                    <input id="${name}" name="${name}" label="${outputOriginal}" type="${formItemType}" value="${formItem.getChild("default_value").text}"/>
                                #end
                            #end
                            <br/>
                  
            #end
        #end
        <br/>
        <br/>
        <script type="text/javascript">
                                var RecaptchaOptions = {
                                    theme : 'clean'
                                };
                            </script>
            <?php
                require_once('../config/recaptchalib.php');
                echo recaptcha_get_html("Key Here");
            ?>
                <br/>

                #if ($callingPageStructure.getChild("submit_button_text").text != '')

                    <input type="submit" value="${callingPageStructure.getChild('submit_button_text').text}"/>
                #else
                    <input type="submit" />
                #end
                #if ($callingPageStructure.getChild("reset").getChild("value").text == 'true')
                    <input type="reset" value="Reset Form"/>
                #end
          
</form>
  <link href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css" rel="stylesheet" type="text/css"/>
  <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.5/jquery.min.js"></script>
  <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js"></script>
  <script type="text/javascript" src="http://jzaefferer.github.com/jquery-validation/jquery.validate.js"></script>
  <script>
  $(document).ready(function() {
    //class datepicker used here to assign jQuery UI datapicker
    $(".datepicker").datepicker();
    $("#webForm").validate();
  });
  </script>
</div>
[system-view:external]
<?php
    } //END NO-POST-FORM-SHOWING
    ?>
[/system-view:external]