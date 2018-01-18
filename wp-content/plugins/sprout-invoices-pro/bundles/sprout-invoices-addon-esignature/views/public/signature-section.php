<style type="text/css">
	#si_signature_wrap {
		width: 450px;
		height: 350px;
		margin: 100px 112px;
	}

</style>
<div id="si_signature_wrap">
	<div id="ctlSignature_Container">
		<script language="javascript" type="text/javascript">
			var ieVer = getInternetExplorerVersion(),
				ieBackCompat = false;
			if ( ieBackCompat ) {
				if ( ieVer >= 9.0 )
					isIE = false;
			}
			if ( ieBackCompat ) {
				document.write("<div id='ctlSignature' style='width:450px;height:300px;'></div>");
			} else {
				document.write("<canvas id='ctlSignature' width='450' height='300'></canvas>");
			}
		</script>
	</div>
	<button type="submit" id="save_signature_via_ajax" class="button"><?php _e( 'Save Signature!', 'sprout-invoices' ) ?></button>
</div><!-- #si_signature_wrap -->
