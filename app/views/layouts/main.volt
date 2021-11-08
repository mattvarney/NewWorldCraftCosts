<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="description" content="">
        <meta name="author" content="">

        <title>{{ titleTag }}</title>

        {{ assets.outputJs('headerJs') }}
        {{ assets.outputCss('headerCss') }}
    </head>

    <body>
        <div class="d-flex" id="wrapper">
{#            {{ partial("layouts/partials/main-sidenav") }}#}
            <div id="page-content-wrapper">
                {{ partial("layouts/partials/main-topnav") }}
                <div class="container-fluid">
                    {% block content %}{% endblock %}
                </div>
            </div>
        </div>

        {{ assets.outputJs('footerJs') }}

        <!-- Menu Toggle Script -->
        <script>
            $("#menu-toggle").click(function(e) {
                e.preventDefault();
                $("#wrapper").toggleClass("toggled");
            });
        </script>
    </body>
</html>