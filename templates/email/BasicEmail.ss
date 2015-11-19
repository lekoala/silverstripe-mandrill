<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="viewport" content="width=device-width"/>
		<title>$Subject</title>
		<% include InkCss %>
		<style type="text/css">
			.template-label {
				color: $HeaderFontColor;
				font-weight: bold;
				font-size: 11px;
			}

			.callout .wrapper {
				padding-bottom: 20px;
			}

			.callout .panel {
				background: $PanelColor;
				border-color: $PanelBorderColor;
				color: $PanelFontColor;
			}

			.sidebar img {
				float:none;
				display:block;
				margin:0 auto;
			}

			.btn {
				display:block;width:auto!important;text-align:center;background:#2ba6cb;
				border:1px solid #2284a1;color:#fff;padding:8px 0;
				-webkit-border-radius:3px;-moz-border-radius:3px;border-radius:3px;
				margin-bottom:8px;
			}
			.btn:hover, .btn:active, .btn:visited {
				color:#fff !important;
				background:#2795b6!important;
			}

			.header {
				background: $HeaderColor;
				color: $HeaderFontColor;
			}

			.footer .wrapper {
				background: $FooterColor;
				color: $FooterFontColor;
			}

			.footer .footer-content {
				padding-top:10px;
				padding-left:10px;
				padding-right:10px;
				font-size:11px;
				text-align:center;
			}
			.footer p {
				color: $FooterFontColor;
				font-size:11px;
				text-align:center;
			}
		</style>
	</head>
	<body>
		<table class="body">
			<tr>
				<td class="center" align="center" valign="top">
					<center>

						<table class="row header">
							<tr>
								<td class="center" align="center">
									<center>

										<table class="container">
											<tr>
												<td class="wrapper last">

													<table class="twelve columns">
														<tr>
															<td class="six sub-columns">
																<% if SiteConfig.EmailLogoTemplate %>
																$SiteConfig.EmailLogoTemplate.SetHeight(50)
																<% end_if %>
															</td>
															<td class="six sub-columns last" style="text-align:right; vertical-align:middle;">
																<span class="template-label">$Subject</span>
															</td>
															<td class="expander"></td>
														</tr>
													</table>

												</td>
											</tr>
										</table>

									</center>
								</td>
							</tr>
						</table>

						<table class="container">
							<tr>
								<td>

									<table class="row">
										<tr>
											<% if Sidebar %>
											<td class="wrapper">

												<table class="eight columns">
													<tr>
														<td>
															$Body
														</td>
														<td class="expander"></td>
													</tr>
												</table>

											</td>
											<td class="wrapper last sidebar">

												<table class="four columns">
													<tr>
														<td class="panel">
															$Sidebar
														</td>
														<td class="expander"></td>
													</tr>
												</table>

											</td>
											<% else %>
											<td class="wrapper last">

												<table class="twelve columns">
													<tr>
														<td>
															$Body
														</td>
														<td class="expander"></td>
													</tr>
												</table>

											</td>
											<% end_if %>
										</tr>
									</table>

									<% if Image %>
									<table class="row">
										<tr>
											<td>

												<table class="twelve columns">
													<tr>
														<td>
															<img src="$Image" alt="$Image" />
														</td>
														<td class="expander"></td>
													</tr>
												</table>

											</td>
										</tr>
									</table>
									<% end_if %>

									<% if Callout %>
									<table class="row callout">
										<tr>
											<td class="wrapper last">

												<table class="twelve columns">
													<tr>
														<td class="panel">
															$Callout
														</td>
														<td class="expander"></td>
													</tr>
												</table>

											</td>
										</tr>
									</table>
									<% end_if %>

									<% if SiteConfig.EmailFooter %>
									<table class="row footer">
										<tr>
											<td class="wrapper last">

												<table class="twelve columns">
													<tr>
														<td align="center" class="footer-content">
															<center>
																$SiteConfig.EmailFooter
															</center>
														</td>
														<td class="expander"></td>
													</tr>
												</table>

											</td>
										</tr>
									</table>
									<% end_if %>

									<!-- container end below -->
								</td>
							</tr>
						</table>

					</center>
				</td>
			</tr>
		</table>
	</body>
</html>