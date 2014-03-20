<div id="pages-controller-cms-content" class="cms-content center " data-layout-type="border" data-pjax-fragment="Content">
	<div class="cms-content-header north">
		<div class="cms-content-header-info">
			<h2>
				<% include CMSBreadcrumbs %>
			</h2>
		</div>	
	</div>

	<div class="cms-content-fields center ui-widget-content cms-panel-padded">
		$SearchForm
		<% if Messages %>
		<table class="ss-gridfield-table">
			<thead>
				<tr class="title">
					<td><% _t('Mandrill.Date','Date') %></td>
					<td><% _t('Mandrill.Sender','Sender') %></td>
					<td><% _t('Mandrill.Recipient','Recipient') %></td>
					<td><% _t('Mandrill.Subject','Subject') %></td>
					<td><% _t('Mandrill.State','State') %></td>
					<td><% _t('Mandrill.Opens','Opens') %></td>
					<td><% _t('Mandrill.Clicks','Clicks') %></td>
				</tr>
			</thead>
			<tbody class="ss-gridfield-items">
				<% loop Messages %>
				<tr class="ss-gridfield-item state-{$State}">
					<td>$Date</td>
					<td>$Sender</td>
					<td>$Recipient</td>
					<td>$Subject</td>
					<td>$State</td>
					<td>$Opens</td>
					<td>$Clicks</td>
				</tr>
				<% end_loop %>
			</tbody>
		</table>
		<% else %>
		<p class="message warning"><% _t('Mandrill.NOMESSAGESMATCH','No messages match your criteria') %></p>

		<% end_if %>
	</div>
</div>