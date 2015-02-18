<div id="pages-controller-cms-content" class="cms-content center " data-layout-type="border" data-pjax-fragment="Content">
	<div class="cms-content-header north">
		<div class="cms-content-header-info">
			<% if CurrentMessage %>
			<a class="ss-ui-button" data-icon="back" href="/admin/mandrill" data-pjax-target="Content"><% _t('BackLink_Button_ss.Back') %></a>
			<% end_if %>
			<% include CMSBreadcrumbs %>
		</div>	
	</div>

	<div class="cms-content-fields center ui-widget-content cms-panel-padded">
		<% if CurrentMessage %>
		<% with CurrentMessage %>
		<div class="MandrillMessage">
			<h3>$subject</h3>
			<p class="infos">
				<span class="state">$state</span> <%t MandrilAdmin.At "at" %> <span class="date">$DateTime</span>
				<% loop TagsList %>
				<span class="tag">$Title</span>
				<% end_loop %>
			</p>
			<% if html %>
			<iframe src="/admin/mandrill/view_message/$_id" style="width:100%;height:400px;background:#fff;"></iframe>
			<% else %>
			<p class="message"><%t MandrilAdmin.ContentNotAvailable "The content of the email is not available anymore. Only recently sent messages are available." %></p>
			<% end_if %>
			<h4>$opens <%t MandrilAdmin.Opens "Opens" %></h4>
			<% loop OpensList %>
			<div class="meta">
				$date, $IpLink, $location, $ua
			</div>
			<% end_loop %>
			<h4>$clicks <%t MandrilAdmin.Clicks "Clicks" %></h4>
			<% loop ClicksList %>
			<div class="meta">
				$date, $IpLink, <a href="$url" target="_blank">$url</a>, $location, $ua
			</div>
			<% end_loop %>
		</div>
		<% end_with %>
		<% else %>

		<div class="ss-tabset">
			<ul>
				<li><a href="#tab-messages"><%t MandrillAdmin.MessagesTab "Messages" %></a></li>
				<li><a href="#tab-hooks"><%t MandrillAdmin.HooksTab "Webhooks" %></a></li>
				<li><a href="#tab-templates"><%t MandrillAdmin.TemplatesTab "Templates" %></a></li>
			</ul>
			<div id="tab-messages">
				$SearchForm
				$ListForm
			</div>
			<div id="tab-hooks">
				<% if WebhookInstalled %>
				$UninstallHookForm
				<% else %>
				$InstallHookForm
				<% end_if %>
			</div>
			<div id="tab-templates">
				templates
			</div>
		</div>
		<% end_if %>
	</div>
</div>