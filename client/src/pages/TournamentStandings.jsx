import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';

import { getStandings } from '../api/api';

import { StandingsTable } from '../components';

export default function TournamentStandings() {
  const { id } = useParams();

  const [standings, setStandings] = useState([]);

  useEffect(() => {
    if (id) loadStandings(parseInt(id));
  }, [id]);

  const loadStandings = async (tournamentId) => {
    const data = await getStandings(tournamentId);
    console.log(data);
    setStandings(data);
  };

  return (
    <div className="container">
      <h1>Swiss Standings</h1>

      <StandingsTable standings={standings} />
    </div>
  );
}
