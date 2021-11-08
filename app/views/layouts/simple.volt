{# app/views/layouts/base.volt #}
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {% block head %}
      {{ assets.outputJs('headerJs') }}
      {{ assets.outputCss('headerCss') }}


    {% endblock %}
  </head>
  <body style='background-color:white; overflow-x:scroll'>
        {# output any flash messages #}
        {{ flash.output() }}

        {#
            this is a handy little tool for debugging, just set {{ debug }}
            to whatever variables you want to see on the screen and the view
            will do a var dump

            just in case you accidentally leave some debug data in the code,
            this will only display in the development run mode
        #}
        {% if session.get('runMode') == 'development' and !(debug is empty) %}
            {{ dump(debug) }}
        {% endif %}

        {% block imnContent %}

        {% endblock %}

        <div style='clear:both'></div>

    <div id="imn-footer" class="container-fluid">
        {% block imnFooter %}

        {% endblock %}
    </div>

    {% block javascript %}


    {{ assets.outputJs('footerJs') }}

    {% endblock %}
  </body>
</html>
