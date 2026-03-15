import React, { useState } from 'react';
import { 
  Activity, 
  CheckCircle2, 
  AlertCircle, 
  Clock, 
  Database, 
  Server,
  RefreshCw,
  Search
} from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { 
  Table, 
  TableBody, 
  TableCell, 
  TableHead, 
  TableHeader, 
  TableRow 
} from "@/components/ui/table";
import { MadeWithDyad } from "@/components/made-with-dyad";

const Index = () => {
  const [searchTerm, setSearchTerm] = useState("");

  // Mock de dados para visualização da interface
  const logs = [
    { id: 1, date: "2024-05-20 10:00:01", task: "Sincronização GLPI", status: "Sucesso", message: "5 tarefas inseridas", duration: "1.2s" },
    { id: 2, date: "2024-05-20 09:50:02", task: "Sincronização GLPI", status: "Erro", message: "Falha na conexão com a API", duration: "0.5s" },
    { id: 3, date: "2024-05-20 09:40:01", task: "Sincronização GLPI", status: "Sucesso", message: "Nenhum dado novo para processar", duration: "0.8s" },
  ];

  return (
    <div className="min-h-screen bg-slate-50 p-4 md:p-8">
      <div className="max-w-7xl mx-auto space-y-8">
        
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b pb-6">
          <div>
            <h1 className="text-3xl font-bold tracking-tight text-slate-900">Monitor de Integração GLPI</h1>
            <p className="text-slate-500">Acompanhamento em tempo real da automação de tarefas via PHP Cron.</p>
          </div>
          <div className="flex items-center gap-3">
            <Badge variant="outline" className="px-3 py-1 bg-green-50 text-green-700 border-green-200 gap-1.5">
              <span className="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
              Backend Online
            </Badge>
            <button className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors font-medium">
              <RefreshCw size={18} />
              Executar Agora
            </button>
          </div>
        </div>

        {/* Stats Grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Card className="border-none shadow-sm bg-white">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-slate-500 uppercase">Total de Execuções (24h)</CardTitle>
              <Activity className="text-indigo-500" size={20} />
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-slate-900">144</div>
              <p className="text-xs text-slate-400 mt-1">Sincronização a cada 10 min</p>
            </CardContent>
          </Card>
          
          <Card className="border-none shadow-sm bg-white">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-slate-500 uppercase">Sucessos</CardTitle>
              <CheckCircle2 className="text-emerald-500" size={20} />
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-slate-900">142</div>
              <p className="text-xs text-emerald-600 mt-1">98.6% de taxa de sucesso</p>
            </CardContent>
          </Card>

          <Card className="border-none shadow-sm bg-white">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-slate-500 uppercase">Falhas</CardTitle>
              <AlertCircle className="text-rose-500" size={20} />
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-slate-900">2</div>
              <p className="text-xs text-rose-600 mt-1">Verificar logs de conectividade</p>
            </CardContent>
          </Card>
        </div>

        {/* Configuration Summary */}
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
          <div className="lg:col-span-1 space-y-6">
            <Card className="border-none shadow-sm">
              <CardHeader>
                <CardTitle className="text-lg">Configurações</CardTitle>
                <CardDescription>Detalhes do ambiente PHP</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center gap-3 text-sm">
                  <Server className="text-slate-400" size={16} />
                  <span className="text-slate-600">GLPI 10 API</span>
                </div>
                <div className="flex items-center gap-3 text-sm">
                  <Database className="text-slate-400" size={16} />
                  <span className="text-slate-600">MySQL Localhost</span>
                </div>
                <div className="flex items-center gap-3 text-sm">
                  <Clock className="text-slate-400" size={16} />
                  <span className="text-slate-600">Intervalo: 10 min</span>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Logs Table */}
          <Card className="lg:col-span-3 border-none shadow-sm">
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle className="text-lg">Logs de Atividade</CardTitle>
                <CardDescription>Histórico estruturado das últimas execuções</CardDescription>
              </div>
              <div className="relative w-64">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                <Input
                  placeholder="Filtrar logs..."
                  className="pl-9 bg-slate-50 border-none"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
              </div>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow className="hover:bg-transparent border-slate-100">
                    <TableHead className="w-[180px]">Data/Hora</TableHead>
                    <TableHead>Tarefa</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Mensagem</TableHead>
                    <TableHead className="text-right">Duração</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {logs.map((log) => (
                    <TableRow key={log.id} className="border-slate-50 hover:bg-slate-50 transition-colors">
                      <TableCell className="font-mono text-xs text-slate-500">{log.date}</TableCell>
                      <TableCell className="font-medium">{log.task}</TableCell>
                      <TableCell>
                        <Badge variant={log.status === 'Sucesso' ? 'secondary' : 'destructive'} className={log.status === 'Sucesso' ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-100 border-none' : ''}>
                          {log.status}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-slate-600">{log.message}</TableCell>
                      <TableCell className="text-right text-slate-400">{log.duration}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
        
        <MadeWithDyad />
      </div>
    </div>
  );
};

export default Index;