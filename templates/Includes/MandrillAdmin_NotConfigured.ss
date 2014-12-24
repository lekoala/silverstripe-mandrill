<div id="pages-controller-cms-content" class="cms-content center " data-layout-type="border" data-pjax-fragment="Content">
	<div class="cms-content-header north">
		<div class="cms-content-header-info">
			<% include CMSBreadcrumbs %>
		</div>	
	</div>

	<div class="cms-content-fields center ui-widget-content cms-panel-padded">
		<div class="message bad">
			<%t MandrilAdmin.NotConfigured "The MandrillMailer is not configured. Please define a MANDRILL_API_KEY constant in your _ss_environment or initialize the mailer yourself by using MandrillMailer::setAsMailer() method " %>
		</div>
	</div>
</div>