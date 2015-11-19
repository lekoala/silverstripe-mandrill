<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="viewport" content="width=device-width"/>
		<title>$Subject</title>
		<% include InkCss %>
	</head>
	<body>
		<table class="body">
			<tr>
				<td class="center" align="center" valign="top">
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
				</td>
			</tr>
		</table>
	</body>
</html>