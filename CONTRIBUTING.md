# Cómo trabajar en equipo

## Una rama por tarea

`main` reúne los avances revisados. Una vez incorporada esta estructura, cada persona crea su rama desde el último `main` y abre un pull request (PR) con destino a `main`. Cada PR debe corresponder a una tarea concreta.

| Persona | Primera rama de implementación | Revisión |
| --- | --- | --- |
| Jorge | `jorge/api-establecimientos` | Michelle revisa la respuesta de la API y Brandon valida los datos |
| Michelle | `michelle/listado-establecimientos` | Jorge revisa la integración y Brandon prueba el listado |
| Brandon | `brandon/validacion-excel` | Jorge revisa el formato de importación y Michelle revisa los campos que verá el usuario |

Ejemplo para iniciar y publicar la tarea de Michelle desde una copia del repositorio.

```bash
git switch main
git pull --ff-only origin main
git switch -c michelle/listado-establecimientos

# Después de implementar y verificar la tarea.
git status --short
git add resources/views/
git diff --cached
git commit -m "Crear listado de establecimientos"
git push -u origin HEAD
```

En GitHub, abrir el PR con base `main` y pedir revisión. Registrar en el PR qué cambió, cómo se probó y si hay una dependencia pendiente. Si `main` avanzó y hace falta incorporar cambios, ejecutar `git fetch origin` y `git merge origin/main` desde la rama de la tarea con el trabajo guardado. Si el conflicto afecta otra carpeta, resolverlo con su responsable.

## Archivos compartidos

- Cada persona implementa en las rutas asignadas en el [README](README.md). Para editar otra ruta, acuerden primero el cambio con su responsable y pídanle revisión.
- Jorge registra las rutas web y de API. Michelle crea las vistas Blade y los archivos públicos de la interfaz. Brandon escribe la importación y las pruebas sin cambiar el esquema de MySQL por su cuenta. Cuando un dato importado requiera una columna nueva, Brandon y Jorge acuerdan el formato y Jorge prepara la migración.
- Jorge coordina los cambios de configuración común, dependencias de raíz y contratos de la API en `docs/`. Michelle y Brandon revisan los cambios del contrato que afecten su trabajo. Brandon mantiene los ejemplos de `datos/ejemplos/` alineados con el contrato.
- Cuando cambie un campo compartido, acuerden el formato en el PR y definan cómo entra el cambio. Pueden integrarlo junto con sus consumidores en un PR coordinado o mantener temporalmente la compatibilidad para integrar los PR en orden.
- Antes de integrar, otra persona revisa el PR. Ejecuten `php artisan test` y comprueben el flujo que aborda el cambio. Jorge coordina el orden de integración y cada persona inicia su siguiente tarea desde el `main` actualizado.

## Preparación en GitHub

Jorge puede invitar a Michelle y Brandon como colaboradores usando sus usuarios de GitHub. Para aplicar el flujo desde GitHub, configurar una protección para `main` que exija un PR y una aprobación. Si el equipo quiere aplicar la misma regla también a administradores, activar la opción que impide omitirla. [Documentación de GitHub sobre ramas protegidas](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches)

El repositorio es público. Usen datos ficticios en los ejemplos y guarden el Excel original y cualquier exportación con datos reales en `datos/privados/` o fuera del repositorio. Revisen los archivos preparados con `git diff --cached` antes de cada commit.
