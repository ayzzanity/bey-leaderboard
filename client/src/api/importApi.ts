import api from './api';

export interface ImportTournamentResponse {
  message: string;
  tournament_id: number;
  tournament_name: string;
}

export const importTournament = async (url: string) => {
  const response = await api.post<ImportTournamentResponse>('/admin/import-tournament', { url });

  return response.data;
};
