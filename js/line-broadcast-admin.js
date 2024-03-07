jQuery(document).ready(function($) {
    $('#line_broadcast_subscription_period').closest('p').hide();
       function toggleFields() {
           if ($('#line_broadcast_enabled').is(':checked')) {
               $('#line_broadcast_audience').closest('p').show();
           } else {
               $('#line_broadcast_audience').closest('p').hide();
           }
       }
   
       toggleFields(); // Call on document ready
   
       $('#line_broadcast_enabled').change(function() {
           toggleFields(); // Call on checkbox state change
       });
   });
   jQuery(document).ready(function($) {
       setTimeout(function() { 
       $('#line_broadcast_audience').change(function() {
           var audienceGroupId = $(this).val();
           if (audienceGroupId) {
               $.ajax({
                   url: ajaxurl, // URL สำหรับการจัดการ Ajax ใน WordPress
                   type: 'POST',
                   data: {
                       action: 'check_audience_group', // ชื่อ action ที่ WordPress จัดการ
                       audienceGroupId: audienceGroupId // ID ของ audience group
                   },
                   success: function(response) {
       console.log(response); // ตรวจสอบโครงสร้างข้อมูล response
       if (response.success && response.data && response.data.audienceCount >= 100) {
           $('#line_broadcast_subscription_period').closest('p').show();
           console.log(response.data.audienceCount);
       } else {
           $('#line_broadcast_subscription_period').closest('p').hide();
           if(response.data) console.log(response.data.audienceCount);
           else console.log("No data");
       }
   }
               });
           }
       });
           },200);
   });
   