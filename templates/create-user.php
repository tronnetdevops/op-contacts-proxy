<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
		<title>GContacts - Add User</title>

		<!-- Bootstrap -->
		<!-- Latest compiled and minified CSS -->
		<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">

		<!-- Optional theme -->
		<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css">

		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">

		<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
		<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
		<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
			
		<style>
			.row.no-gutter {
				margin-left: 0;
				margin-right: 0;
			}

			.row.no-gutter [class*='col-']:not(:first-child),
			.row.no-gutter [class*='col-']:not(:last-child) {
				padding-right: 0;
				padding-left: 0;
			}
		</style>
	</head>
	<body>
		
		<header role="header">
			<nav role="navigator" class="navbar navbar-default">
			    <div class="container-fluid">
				    <div class="navbar-header">
							<a class="navbar-brand" href="#">Import Account</a>
				    </div>
					
				    <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
						<ul class="nav navbar-nav navbar-right">
							<li class="active hide">
								<a href="#">
									<i class="fa fa-home"></i>
									Home
								</a>
							</li>
						</ul>
					</div>
				</div>
			</nav>
		</header>
		
		<section role="main" class="container">
			<div class="row">
				
				<div class="col-lg-12">
					
					<!-- TASKS -->
					<div class="row">
						<div class="col-lg-12">
							<div class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title">
										<i class="fa fa-cubes"></i>
										Get the Ontraport to Google Contacts App
									</h3>
								</div>
								<div class="panel-body">
									<form action="/">
										<input type="hidden" name="cb_op_action_oauth" value="true" />
										
									  <fieldset class="form-group">
									    <label for="exampleInputEmail1">Contact Owner Name</label>
									    <input name="owner" type="text" class="form-control" id="accountOwner" placeholder="Enter Contact Owner Name">
									    <small class="text-muted">This name is how we'll identify your Ontraport account. Make sure it is exactly as it appears in the "Contact Owner" field of your Lead Information section of your contacts!</small>
									  </fieldset>
										
									  <button type="submit" class="btn btn-primary">Submit</button>
										
									</form>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>
		
		<footer role="footer">
			
		</footer>
		
	    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
	    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>

		<!-- Latest compiled and minified JavaScript -->
		<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
	
		<script src="http://code.highcharts.com/highcharts.js"></script>
		<script src="http://code.highcharts.com/modules/exporting.js"></script>
	
		<script>
			// Load the fonts
			Highcharts.createElement('link', {
			   href: '//fonts.googleapis.com/css?family=Unica+One',
			   rel: 'stylesheet',
			   type: 'text/css'
			}, null, document.getElementsByTagName('head')[0]);
			Highcharts.setOptions({
			    lang: {
			        thousandsSep: ','
			    }
			});
			
			$(function () {

			    $(document).ready(function () {
					
					
					var colors = Highcharts.getOptions().colors,
					    categories = ['Secured', 'Submitted Not Secured'],
					    data = [{
					        y: Math.round((45/77) * 100),
					        color: colors[0],
					        drilldown: {
					            name: 'Mine vs Company',
					            categories: ['My Secured Leads'],
					            data: [Math.round((77/578) * 100)],
					            color: colors[0]
					        }
					    },
					    {
					        y: Math.round( ( (77-45) / 77) * 100),
					        color: colors[0],
					        drilldown: {
					            name: 'Mine vs Company',
					            categories: ['Overall Company Leads'],
					            data: [Math.round( ( (578-77) / 578) * 100)],
					            color: colors[1]
					        }
					    }],
					    browserData = [],
					    versionsData = [],
					    i,
					    j,
					    dataLen = data.length,
					    drillDataLen,
					    brightness;


					// Build the data arrays
					for (i = 0; i < dataLen; i += 1) {

					    // add browser data
					    browserData.push({
					        name: categories[i],
					        y: data[i].y,
					        color: data[i].color
					    });

					    // add version data
					    drillDataLen = data[i].drilldown.data.length;
					    for (j = 0; j < drillDataLen; j += 1) {
					        brightness = 0.2 - (j / drillDataLen) / 5;
					        versionsData.push({
					            name: data[i].drilldown.categories[j],
					            y: data[i].drilldown.data[j],
					            color: Highcharts.Color(data[i].color).brighten(brightness).get()
					        });
					    }
					}
	

			        // Build the chart
			        $('#ff-profit-pie-chart').highcharts({
				        chart: {
				            type: 'pie'
				        },
				        title: {
				            text: 'Leads Success Rate'
				        },
				        subtitle: {
				            text: ''
				        },
				        yAxis: {
				            title: {
				                text: ''
				            }
				        },
				        plotOptions: {
				            pie: {
				                shadow: false,
				                center: ['50%', '50%'],
			                    allowPointSelect: true,
			                    cursor: 'pointer',
			                    dataLabels: {
			                        enabled: true
			                    },
			                    showInLegend: true
				            }
				        },
				        tooltip: {
				            valueSuffix: '%'
				        },
				        series: [{
				            name: 'My Leads',
				            data: browserData,
				            size: '60%',
				            dataLabels: {
				                formatter: function () {
				                    return this.y > 5 ? this.point.name : null;
				                },
				                color: '#ffffff',
				                distance: -30
				            }
				        }, {
				            name: 'Overall Company',
				            data: versionsData,
				            size: '80%',
				            innerSize: '60%',
				            dataLabels: {
				                formatter: function () {
				                    // display only if larger than 1
				                    return this.y > 1 ? '<b>' + this.point.name + ':</b> ' + this.y + '%' : null;
				                }
				            }
				        }]
				    });
					
					$("#ff-success-pie-chart").highcharts({
						chart: {
						    type: 'column'
						},
						title: {
						    text: 'Revenue'
						},
						subtitle: {
						    text: ''
						},
						xAxis: {
						    categories: [
						        'Prior Period',
						        'Current Period',
						        'Prior Year',
						        'Current Year'
						    ],
						    crosshair: true
						},
						yAxis: {
						    min: 0,
						    title: {
						        text: 'Total Dollars'
						    }
						},
						tooltip: {
						    headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
						    pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
						        '<td style="padding:0"><b>${point.y}</b></td></tr>',
						    footerFormat: '</table>',
						    shared: true,
						    useHTML: true
						},
						plotOptions: {
						    column: {
						        pointPadding: 0.2,
						        borderWidth: 0
						    }
						},
						series: [{
						    name: 'Revenue',
						    data: [176276, 36577, 578116, 78241]

						}]
					});
			    });
			});
		</script>
			
			
			
		<script>
		$(function(){
			
		})
		</script>
	</body>
</html>
