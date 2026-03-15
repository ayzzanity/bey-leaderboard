import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';

import type { Standing } from '../types/Standing';
import { getStandings } from '../api/api';

import StandingsTable from '../components/StandingsTable';

export default function TournamentStandings() {
  const { id } = useParams();

  const [standings, setStandings] = useState<Standing[]>([]);

  useEffect(() => {
    if (id) loadStandings(parseInt(id));
  }, [id]);

  const loadStandings = async (tournamentId: number) => {
    const data = await getStandings(tournamentId);
    setStandings(data);
  };

  return (
    <div className="container">
      <h1>Swiss Standings</h1>

      <StandingsTable standings={standings} />
    </div>
  );
}
