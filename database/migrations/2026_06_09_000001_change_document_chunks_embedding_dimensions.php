<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $dimensions = config('ai.embedding_dimensions', 768);

        // 既有向量維度與新 embedding 模型不同，無法直接比對，
        // 因此重建 embedding 欄位並清空既有向量（文件需重新處理）。
        DB::statement('DROP INDEX IF EXISTS document_chunks_embedding_idx');
        DB::statement('ALTER TABLE document_chunks DROP COLUMN IF EXISTS embedding');
        DB::statement("ALTER TABLE document_chunks ADD COLUMN embedding vector({$dimensions})");
        DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS document_chunks_embedding_idx');
        DB::statement('ALTER TABLE document_chunks DROP COLUMN IF EXISTS embedding');
        DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(1536)');
        DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');
    }
};
