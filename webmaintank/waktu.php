<?php
$a = 'foo'; // $a now holds PHP string foo
?>
<script>
    var a = '<?php echo $a; ?>'; //outputting string foo in context of JS
                                 //must wrap in quotes so that it is still string foo when JS does execute
                                 //when this DOES execute in the browser, PHP will have already completed all processing and exited
</script>
<?php
//do something else with $a
//JS still hasn't executed at this point
?>