<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articulos', function (Blueprint $table) {
            $table->id();

            // ----------------------------------------------------------
            // Identidad y URL
            // ----------------------------------------------------------
            // Primera columna unica del proyecto. Es una desviacion deliberada
            // de la convencion: el slug es la URL publica, y dos articulos con
            // el mismo slug significan una URL que sirve contenido distinto
            // segun el orden de la consulta. Tiene que fallar en la base, no en
            // la validacion del formulario.
            $table->string('slug')->unique();

            $table->string('titulo');
            $table->string('meta_title', 60);
            $table->string('meta_description', 160);
            $table->text('resumen');

            // ----------------------------------------------------------
            // Contenido
            // ----------------------------------------------------------
            // Se guarda el Markdown original Y el HTML renderizado. El HTML se
            // recalcula al guardar, no en cada visita: 30 articulos de 2000
            // palabras son ~200 ms de CommonMark que no tiene sentido pagar en
            // cada peticion. El Markdown se conserva para poder reeditar y para
            // el comando de exportacion a git.
            $table->longText('contenido');
            $table->longText('contenido_html')->nullable();
            $table->json('toc')->nullable();
            $table->unsignedSmallInteger('palabras')->default(0);

            // ----------------------------------------------------------
            // Clasificacion
            // ----------------------------------------------------------
            $table->enum('cluster', [
                'pagespeed-core-web-vitals',
                'analytics-ga4',
                'sem-google-ads',
                'seo-organico',
                'desarrollo-web',
                'social-media',
            ]);

            $table->enum('estado', ['borrador', 'publicado', 'archivado'])->default('borrador');

            $table->foreignId('autor_id')->constrained('users')->restrictOnDelete();

            // ----------------------------------------------------------
            // Fechas editoriales
            // ----------------------------------------------------------
            // Deliberadamente separadas de created_at / updated_at. updated_at
            // cambia al corregir una coma; publicar eso como dateModified en el
            // schema es una senal de manipulacion. Estas dos las decide quien
            // edita, y son las que van al JSON-LD y al <lastmod> del sitemap.
            $table->date('fecha_publicacion')->nullable();
            $table->date('fecha_actualizacion')->nullable();

            // ----------------------------------------------------------
            // Imagenes
            // ----------------------------------------------------------
            $table->string('imagen_destacada')->nullable();
            $table->string('imagen_alt')->nullable();
            $table->string('og_image')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // El listado publico filtra siempre por estado y ordena por fecha.
            $table->index(['estado', 'fecha_publicacion']);
            $table->index(['cluster', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articulos');
    }
};
