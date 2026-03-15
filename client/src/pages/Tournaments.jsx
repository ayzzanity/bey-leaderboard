import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Button } from 'antd';

import { getTournaments } from '../api/api';

import { TournamentList } from '../components';

export default function Tournaments() {
  const [tournaments, setTournaments] = useState([]);

  useEffect(() => {
    loadTournaments();
  }, []);

  const loadTournaments = async () => {
    const data = await getTournaments();
    setTournaments(data);
  };

  return (
    <div className="container">
      <div className="flex justify-between items-center mx-2">
        <h1>Tournaments</h1>

        <Link to="/admin/import">
          <Button>Import Tournament</Button>
        </Link>
      </div>

      {tournaments.length === 0 ? <p>No tournaments imported yet.</p> : <TournamentList tournaments={tournaments} />}
    </div>
  );
}
