<?php
?><!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>JobNet API Docs</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
  <style>
    html, body {
      margin: 0;
      padding: 0;
      background: #f5f7fb;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }

    .topbar {
      background: linear-gradient(90deg, #0f172a 0%, #1d4ed8 100%);
      color: #ffffff;
      padding: 14px 20px;
      font-size: 14px;
      letter-spacing: 0.3px;
    }

    #swagger-ui {
      max-width: 1200px;
      margin: 20px auto;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 8px 30px rgba(15, 23, 42, 0.15);
      background: #ffffff;
    }
  </style>
</head>
<body>
  <div class="topbar">JobNet API Documentation</div>
  <div id="swagger-ui"></div>

  <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
  <script>
    window.ui = SwaggerUIBundle({
      url: './openapi.yaml',
      dom_id: '#swagger-ui',
      deepLinking: true,
      presets: [
        SwaggerUIBundle.presets.apis,
        SwaggerUIStandalonePreset
      ],
      layout: 'StandaloneLayout',
      displayRequestDuration: true,
      persistAuthorization: true,
      tryItOutEnabled: true
    });
  </script>
</body>
</html>
