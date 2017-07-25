<style type="text/css">

.clearfix:after {
	content: "";
	display: table;
	clear: both;
}

.wrap * {
	box-sizing: border-box;
}

.wrap {
	padding: 12px;
	box-sizing: border-box;
}

.wrap h1.wp-heading-inline {
	background: #ce1025;
	color: #fff;
	width: 100%;
	display: block;
	margin: 10px 0 15px 0;
	font-size: 15px;
	background-image: url(<?php echo plugins_url('/assets/logo.png', __FILE__) ?>);
	background-repeat: no-repeat;
	background-position: 20px center;
	background-size: auto 20px;
	line-height: 60px;
	position: relative;
	padding: 0px 0px 0px 150px;
}

h1.wp-heading-inline:before {
	content: '';
	width: 1px;
	height: 30px;
	background: rgba(255, 255, 255, .4);
	position: absolute;
	left: 135px;
	top: 15px;
}

.ss-panel {
	background: #fff;
	box-sizing: border-box;
	margin: 15px 0 30px 0;
	position: relative;
	box-shadow: 3px 3px 10px rgba(0, 0, 0, .1);
	padding: 40px;
	/*float: left;*/
	border-left: 10px solid #ccc;
}

.ss-panel h3 {
	font-size: 23px;
	font-weight: 400;
	margin-top: 0;
}

.ss-panel label {
	display: block;
	font-size: 14px;
	font-weight: 100;
	margin-top: 10px;
	margin-bottom: 3px;
	color: #333;
}

.ss-panel input[type="text"],
.ss-panel select,
.ss-panel textarea {
	box-sizing: border-box;
	display: block;
	width: 100%;
	padding: 6px 12px;
	font-size: 14px;
	line-height: 1.42857143;
	color: #555;
	background-color: #fff;
	background-image: none;
	border: 1px solid #ccc;
	border-radius: 4px;
	-webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, .075);
	box-shadow: inset 0 1px 1px rgba(0, 0, 0, .075);
	-webkit-transition: border-color ease-in-out .15s, -webkit-box-shadow ease-in-out .15s;
	-o-transition: border-color ease-in-out .15s, box-shadow ease-in-out .15s;
	transition: border-color ease-in-out .15s, box-shadow ease-in-out .15s;
}

.ss-panel input[type="checkbox"] {
	display: inline-block;
}

.ss-panel input[type="text"],
.ss-panel select {
	height: 34px;
}

.ss-panel.ss-faq-panel p {
	font-size: 15px;
}

.ss-panel.ss-faq-panel pre {
	display: inline-block;
	font-size: 16px;
	background-color: #444;
	color: #fff;
	width: auto;
	padding: 10px 15px;
	margin: 0;
	white-space: normal;
	line-height: 22px;
}

.ss-checkbox {
	display: block;
	width: 100%;
	margin: 15px 0px;
}

.ss-checkbox .ss-subtext {
	font-size: 10px;
	margin-left: 33px;
}

.ss-panel label.ss-checkbox-label {
	display: inline-block;
	margin: 0;
	position: relative;
	top: -2px;
}

.ss-report-button {
	margin-top: 5px !important;
}

.hidden-small {
	display: none;
}

.ss-top-container,
.ss-lower-container {
	display: block;
	width: 100%;
}

.ss-top-container .ss-panel,
.ss-lower-container .ss-panel {
	width: 100%;
}

.ss-checkbox {
	margin: 15px 0px;
}

@media only screen and (min-width: 480px) {
	.ss-top-container .ss-panel,
	.ss-lower-container .ss-panel {
		width: 90%;
		display: block;
		margin-left: auto;
		margin-right: auto;
	}
}

@media only screen and (min-width: 768px) {
	.ss-top-container .ss-panel,
	.ss-lower-container .ss-panel {
		width: 80%;
	}
	.ss-report-otions-panel {
		width: 400px;
	}
	.hidden-small {
		display: inline-block;
	}
}

@media only screen and (min-width: 961px) {
	.ss-top-container .ss-panel.ss-api-credentials {
		width: 100%;
	}
	.ss-top-container .ss-panel.ss-general-options,
	.ss-top-container .ss-panel.ss-fraud-options {
		width: 48%;
		float: left;
		min-height: 420px;
	}
	.ss-top-container .ss-panel.ss-general-options {
		margin-right: 4%;
	}
	.ss-top-container .ss-panel,
	.ss-lower-container .ss-panel {
		width: 100%;
	}
}

@media only screen and (min-width: 1441px) {
	.ss-top-container .ss-panel.ss-api-credentials,
	.ss-top-container .ss-panel.ss-general-options,
	.ss-top-container .ss-panel.ss-fraud-options {
		float: left;
		margin-right: 2%;
		width: 32%;
		min-height: 405px;
	}
	.ss-fraud-options {
		margin-right: 0 !important;
	}
	.ss-lower-container {
		width: 100%;
	}
	.ss-lower-container .ss-panel {
		float: left;
		width: 100%;
		max-width: 1500px;
	}
	.ss-lower-container,
	.ss-top-container {
		max-width: 1500px;
	}
}

</style>