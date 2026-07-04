# Context — documentación de origen del BEER Lab

Esta carpeta reúne los documentos que fundamentan el proyecto del BEER Lab
(*Behavioral & Experimental Economics Research Lab*) del Departamento de Economía,
Tec de Monterrey, Campus Monterrey. Es material de **insumo**: no se modifica al
desplegar ORSEE, pero define para qué sirve el sistema.

## Índice

| Archivo | Contenido | Relevancia para ORSEE |
|---|---|---|
| `00_Insumos/Propuesta_Laboratorio_Economia.pdf` | Propuesta original de infraestructura del laboratorio (sala en Aulas VI, 30 PCs, divisiones, cámaras, lab móvil, presupuesto). | Justifica el laboratorio; incluye el **servidor ORSEE** como recurso comprometido. |
| `00_Insumos/canvas_negocio_social.pptx` | Canvas de modelo de negocio social: objetivo, problema, solución, propuesta de valor, canales, costos. | Confirma **ORSEE como canal de reclutamiento y comunicación** con participantes, y oTree/z-Tree como software experimental. |
| `00_Insumos/Notas de reunion.docx` | Notas de la reunión inicial del brazo de docencia. | Lista experimentos clásicos por unidad de formación y el rol de los becarios como *lab managers* (operadores de ORSEE). |
| `01_Ante_Proyecto/Ante_Proyecto_BEER_Lab_Docencia.docx` | **Anteproyecto operativo**: componentes de know-how, propuesta de uso, fases, equipo de servicio becario, indicadores, riesgos. | Define el **banco de participantes (ORSEE)** como componente 3.3, el proceso de solicitud de sesiones y el rol *Lab Manager Senior* que opera ORSEE. |
| `01_Ante_Proyecto/build_anteproyecto.py` | Script `python-docx` que genera el anteproyecto `.docx`. | Trazabilidad del documento anterior. |

## Cómo se conecta con este repositorio

El anteproyecto (§3.3) compromete un **banco de participantes operativo soportado por
ORSEE** para automatizar convocatorias, registrar participación histórica y
seleccionar muestras por criterios demográficos. **Este repositorio es la
implementación de ese componente**: el despliegue configurado de ORSEE 3.4.0. Ver
[`../DEPLOYMENT.md`](../DEPLOYMENT.md) para ponerlo en línea y
[`../README.md`](../README.md) para la estructura general.

Mapa rápido documento → sistema:

- *Portafolio de experimentos por UF* (Notas, Anteproyecto §5) → se corren en **oTree**;
  ORSEE agenda y recluta las sesiones (`DEPLOYMENT.md` §7).
- *Proceso de solicitud de sesión* (Anteproyecto §4.3) → calendario y reclutamiento de
  ORSEE (checklist de configuración en `DEPLOYMENT.md` §6).
- *Rol Lab Manager Senior "Operar el sistema ORSEE"* (Anteproyecto §7.4) → usuario
  administrador de ORSEE con los permisos correspondientes (`DEPLOYMENT.md` §5).
