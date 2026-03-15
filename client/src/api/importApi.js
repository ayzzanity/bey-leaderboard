import api from './api';

export const importTournament = async (url) => {
  const response = await api.post('/admin/import-tournament', { url });

  return response.data;
};
