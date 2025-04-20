<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Nail Polish Tracker</title>
    <style>
        body {
            background: linear-gradient(to bottom right, #F8BBD0, #FFF0F5);
            color: #333333;
            font-family: sans-serif;
            margin: 0;
            padding: 1.5rem;
        }

        .container {
            max-width: 800px;
            margin: auto;
        }

        .header {
            color: #E91E63;
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
        }

        .card {
            background-color: #ffffff;
            border-left: 6px solid #C2185B;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .button {
            background-color: #C2185B;
            color: white;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
        }

        .button:hover {
            background-color: #E91E63;
        }

        .highlight {
            background-color: #FFD700;
            color: #333;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">
    <h1 class="header">Suzanne's Nail Polish Tracker</h1>
    @yield('content')
</div>
</body>
</html>
