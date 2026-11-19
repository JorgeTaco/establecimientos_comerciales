# Datos y validación

Brandon prepara aquí las reglas de importación y los ejemplos ficticios. El primer avance cubre el mapeo de columnas, la normalización de respuestas, la revisión de coordenadas y la detección de posibles duplicados. Los casos dudosos pasan a revisión antes de fusionar fichas. El importador se implementará como un comando de Artisan una vez acordado el formato con Jorge.

El [ejemplo del listado](ejemplos/establecimientos.json) sigue el [contrato inicial](../docs/contrato-establecimientos.md). Los seeders de datos ficticios van en [`database/seeders/`](../database/seeders/) y las pruebas automatizadas en [`tests/`](../tests/). Para la primera prueba, abrir el listado en un celular y comprobar que los tres estados de recepción de propuestas se distinguen con claridad. Para el piloto, verificar que un emprendedor pueda registrar un producto, encontrar comercios compatibles, contactar uno y guardar el resultado.
